<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetComponent;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $techUser;

    protected User $regularUser;

    protected Branch $hqBranch;

    protected Department $financeDept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->adminUser = User::where('email', 'admin@cbvh.com.kh')->firstOrFail();

        $this->techUser = User::factory()->create([
            'name' => 'Dara Sam (Tech)',
            'email' => 'tech.dara@cbvh.com.kh',
            'is_active' => true,
            'can_access_portal' => true,
        ]);
        $this->techUser->roles()->attach(Role::where('name', 'technician')->firstOrFail());

        $this->regularUser = User::factory()->create([
            'name' => 'Bopha Staff',
            'email' => 'bopha.staff@cbvh.com.kh',
            'is_active' => true,
        ]);
        $this->regularUser->roles()->attach(Role::where('name', 'requester')->firstOrFail());

        $this->hqBranch = Branch::where('code', 'HQ-PP')->firstOrFail();
        $this->financeDept = Department::where('code', 'FIN')->firstOrFail();
    }

    public function test_it_staff_can_view_asset_registry_and_categories(): void
    {
        $this->actingAs($this->techUser);

        $response = $this->get(route('portal.assets.index'));
        $response->assertOk();
        $response->assertSee('IT Assets, Equipment & Spare Stock');
        $response->assertSee('Workstations & Laptops');
        $response->assertSee('Meeting & Display Equipment');
    }

    public function test_it_staff_can_register_personal_asset(): void
    {
        $this->actingAs($this->techUser);

        $cat = AssetCategory::where('code', 'WORKSTATION')->firstOrFail();

        $response = $this->post(route('portal.assets.store'), [
            'asset_tag' => 'CBVH-PC-0999',
            'name' => 'Accountant Lead Desktop',
            'category_id' => $cat->id,
            'ownership_type' => 'personal',
            'branch_id' => $this->hqBranch->id,
            'department_id' => $this->financeDept->id,
            'assigned_user_id' => $this->regularUser->id,
            'brand' => 'Dell',
            'model' => 'OptiPlex 7090',
            'serial_number' => 'SN-DEL-12345',
            'status' => 'in_use',
            'specs_cpu' => 'i7-11700',
            'specs_ram' => '16GB DDR4',
            'specs_storage' => '512GB NVMe SSD',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assets', [
            'asset_tag' => 'CBVH-PC-0999',
            'name' => 'Accountant Lead Desktop',
            'ownership_type' => 'personal',
            'assigned_user_id' => $this->regularUser->id,
            'brand' => 'Dell',
            'model' => 'OptiPlex 7090',
        ]);
    }

    public function test_it_staff_can_register_shared_room_asset_without_employee(): void
    {
        $this->actingAs($this->techUser);

        $cat = AssetCategory::where('code', 'MEETING')->firstOrFail();

        $response = $this->post(route('portal.assets.store'), [
            'asset_tag' => 'CBVH-MEET-0001',
            'name' => 'HQ Floor 3 - Meeting Room A Slide Projector',
            'category_id' => $cat->id,
            'ownership_type' => 'shared',
            'branch_id' => $this->hqBranch->id,
            'department_id' => null,
            'location_details' => 'Floor 3, Meeting Room A',
            'brand' => 'ViewSonic',
            'model' => 'PX701HD',
            'serial_number' => 'SN-VIEW-8899',
            'status' => 'in_use',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('assets', [
            'asset_tag' => 'CBVH-MEET-0001',
            'name' => 'HQ Floor 3 - Meeting Room A Slide Projector',
            'ownership_type' => 'shared',
            'assigned_user_id' => null,
            'location_details' => 'Floor 3, Meeting Room A',
        ]);
    }

    public function test_it_staff_can_add_spare_parts_to_storage_and_install_into_pc(): void
    {
        $this->actingAs($this->techUser);

        // 1. Add 2x loose 8GB RAM sticks into spare inventory
        $spareResponse = $this->post(route('portal.assets.spares.store'), [
            'component_type' => 'ram',
            'brand_model' => 'Kingston Fury Beast 8GB DDR4',
            'capacity_or_specs' => '8GB 3200MHz',
            'branch_id' => $this->hqBranch->id,
            'quantity' => 2,
            'notes' => 'Shelf B - IT Room',
        ]);
        $spareResponse->assertRedirect();

        $this->assertEquals(2, AssetComponent::where('brand_model', 'Kingston Fury Beast 8GB DDR4')->where('status', 'in_stock')->count());

        $sparePart = AssetComponent::where('brand_model', 'Kingston Fury Beast 8GB DDR4')->firstOrFail();

        // 2. Create a PC
        $cat = AssetCategory::where('code', 'WORKSTATION')->firstOrFail();
        $asset = Asset::create([
            'asset_tag' => 'CBVH-PC-0100',
            'name' => 'Reception PC',
            'category_id' => $cat->id,
            'ownership_type' => 'shared',
            'branch_id' => $this->hqBranch->id,
            'status' => 'in_use',
        ]);

        // 3. Install 1 spare RAM stick into the PC
        $installResponse = $this->post(route('portal.assets.components.store', $asset->id), [
            'source_mode' => 'from_stock',
            'spare_component_id' => $sparePart->id,
        ]);
        $installResponse->assertRedirect();

        $sparePart->refresh();
        $this->assertEquals('installed', $sparePart->status);
        $this->assertEquals($asset->id, $sparePart->asset_id);
    }

    public function test_technician_can_attach_asset_to_ticket_and_record_part_replacement(): void
    {
        $this->actingAs($this->techUser);

        // 1. Create Asset
        $cat = AssetCategory::where('code', 'WORKSTATION')->firstOrFail();
        $asset = Asset::create([
            'asset_tag' => 'CBVH-PC-0077',
            'name' => 'Battambang Cashier PC',
            'category_id' => $cat->id,
            'ownership_type' => 'shared',
            'branch_id' => $this->hqBranch->id,
            'status' => 'in_use',
        ]);

        // 2. Create Ticket
        $ticketCat = TicketCategory::firstOrFail();
        $ticket = Ticket::create([
            'ticket_code' => 'IT-202611-9999',
            'ticket_type' => 'incident',
            'category_id' => $ticketCat->id,
            'branch_id' => $this->hqBranch->id,
            'requester_id' => $this->regularUser->id,
            'subject' => 'Cashier computer randomly freezing',
            'description' => 'System freezes when scanning items',
            'status' => 'in_progress',
            'current_technician_id' => $this->techUser->id,
        ]);

        // 3. Attach Asset to Ticket
        $attachResponse = $this->post(route('portal.tickets.assets.attach', $ticket->id), [
            'asset_id' => $asset->id,
        ]);
        $attachResponse->assertRedirect();

        $this->assertDatabaseHas('ticket_assets', [
            'ticket_id' => $ticket->id,
            'asset_id' => $asset->id,
        ]);

        // 4. Log Part Replacement under Ticket
        $partResponse = $this->post(route('portal.tickets.assets.parts', $ticket->id), [
            'asset_id' => $asset->id,
            'action' => 'replaced',
            'description' => 'Replaced faulty 8GB DDR4 RAM in Slot 1 with new stick from stock',
        ]);
        $partResponse->assertRedirect();

        $this->assertDatabaseHas('ticket_asset_parts', [
            'ticket_id' => $ticket->id,
            'asset_id' => $asset->id,
            'action' => 'replaced',
            'description' => 'Replaced faulty 8GB DDR4 RAM in Slot 1 with new stick from stock',
        ]);

        // 5. Verify the Asset Show view now displays this ticket in its Permanent History!
        $showResponse = $this->get(route('portal.assets.show', $asset->id));
        $showResponse->assertOk();
        $showResponse->assertSee('IT-202611-9999');
        $showResponse->assertSee('Cashier computer randomly freezing');
        $showResponse->assertSee('Replaced faulty 8GB DDR4 RAM in Slot 1 with new stick from stock');
    }

    public function test_recording_part_from_spare_stock_auto_installs_component_and_shows_prior_ticket_history(): void
    {
        $this->actingAs($this->techUser);

        $cat = AssetCategory::where('code', 'WORKSTATION')->firstOrFail();
        $asset = Asset::create([
            'asset_tag' => 'CBVH-PC-0088',
            'name' => 'Staff Requester PC',
            'category_id' => $cat->id,
            'ownership_type' => 'personal',
            'branch_id' => $this->hqBranch->id,
            'assigned_user_id' => $this->regularUser->id,
            'status' => 'in_use',
        ]);

        $spareRam = AssetComponent::create([
            'branch_id' => $this->hqBranch->id,
            'component_type' => 'ram',
            'brand_model' => 'Kingston 16GB DDR4',
            'capacity_or_specs' => '16GB DDR4 3200MHz',
            'status' => 'in_stock',
        ]);

        $ticketCat = TicketCategory::firstOrFail();

        // Older ticket already resolved for this machine
        $oldTicket = Ticket::create([
            'ticket_code' => 'IT-202610-8881',
            'ticket_type' => 'incident',
            'category_id' => $ticketCat->id,
            'branch_id' => $this->hqBranch->id,
            'requester_id' => $this->regularUser->id,
            'subject' => 'Old screen flicker',
            'description' => 'Display flickering.',
            'status' => 'resolved_confirmed',
        ]);
        $oldTicket->assets()->attach($asset->id);

        // New ticket opened by same user
        $newTicket = Ticket::create([
            'ticket_code' => 'IT-202611-8882',
            'ticket_type' => 'incident',
            'category_id' => $ticketCat->id,
            'branch_id' => $this->hqBranch->id,
            'requester_id' => $this->regularUser->id,
            'subject' => 'Needs more memory',
            'description' => 'Upgrade RAM',
            'status' => 'in_progress',
        ]);

        // Attach asset and verify ticket detail displays prior ticket badge and spare component stock option
        $newTicket->assets()->attach($asset->id);

        $detailResponse = $this->get(route('portal.tickets.show', $newTicket->id));
        $detailResponse->assertOk();
        $detailResponse->assertSee('1 prior ticket');
        $detailResponse->assertSee('Kingston 16GB DDR4');

        // Log part replacement using the stock component without custom text -> auto-generates description & marks installed
        $partResponse = $this->post(route('portal.tickets.assets.parts', $newTicket->id), [
            'asset_id' => $asset->id,
            'action' => 'installed',
            'component_id' => $spareRam->id,
            'description' => '',
        ]);
        $partResponse->assertRedirect();

        $spareRam->refresh();
        $this->assertEquals('installed', $spareRam->status);
        $this->assertEquals($asset->id, $spareRam->asset_id);

        $this->assertDatabaseHas('ticket_asset_parts', [
            'ticket_id' => $newTicket->id,
            'asset_id' => $asset->id,
            'component_id' => $spareRam->id,
            'action' => 'installed',
        ]);

        // Attempt to double-claim the same component on another ticket must fail cleanly
        $overlapTicketResponse = $this->post(route('portal.tickets.assets.parts', $oldTicket->id), [
            'asset_id' => $asset->id,
            'action' => 'replaced',
            'component_id' => $spareRam->id,
            'description' => 'Overlap attempt',
        ]);
        $overlapTicketResponse->assertSessionHasErrors('error');

        // Attempt to assign the same already-installed component from asset detail must fail cleanly
        $otherAsset = Asset::create([
            'asset_tag' => 'CBVH-PC-0089',
            'name' => 'Another PC',
            'category_id' => $cat->id,
            'ownership_type' => 'personal',
            'branch_id' => $this->hqBranch->id,
            'status' => 'in_use',
        ]);
        $overlapAssetResponse = $this->post(route('portal.assets.components.store', $otherAsset->id), [
            'source_mode' => 'from_stock',
            'spare_component_id' => $spareRam->id,
        ]);
        $overlapAssetResponse->assertSessionHasErrors('error');

        // Confirm the original PC still owns the RAM exclusively
        $spareRam->refresh();
        $this->assertEquals($asset->id, $spareRam->asset_id);
        $this->assertEquals('installed', $spareRam->status);
    }
}
