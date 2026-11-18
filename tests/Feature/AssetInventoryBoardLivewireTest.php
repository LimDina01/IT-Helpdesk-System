<?php

namespace Tests\Feature;

use App\Livewire\Portal\AssetInventoryBoard;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetComponent;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Role;
use App\Models\SoftwareLicense;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetInventoryBoardLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $techUser;

    protected Branch $branch;

    protected Department $dept;

    protected AssetCategory $category;

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

        $this->branch = Branch::firstOrFail();
        $this->dept = Department::firstOrFail();
        $this->category = AssetCategory::firstOrFail();
    }

    public function test_asset_inventory_board_renders_successfully(): void
    {
        Livewire::actingAs($this->techUser)
            ->test(AssetInventoryBoard::class)
            ->assertStatus(200)
            ->assertSee('Hardware Assets')
            ->assertSee('Spare Parts &amp; Components', false)
            ->assertSee('Stock &amp; Lifecycle Report', false)
            ->assertSee('Software &amp; Licenses', false);
    }

    public function test_assets_index_page_mounts_livewire_component(): void
    {
        $this->actingAs($this->techUser)
            ->get(route('portal.assets.index'))
            ->assertStatus(200)
            ->assertSeeLivewire(AssetInventoryBoard::class);
    }

    public function test_tab_switching_morphs_cleanly(): void
    {
        Livewire::actingAs($this->techUser)
            ->test(AssetInventoryBoard::class)
            ->assertSet('currentTab', 'assets')
            ->call('setTab', 'components')
            ->assertSet('currentTab', 'components')
            ->assertSee('All Component Types')
            ->call('setTab', 'licenses')
            ->assertSet('currentTab', 'licenses')
            ->assertSee('Software Applications')
            ->call('setTab', 'report')
            ->assertSet('currentTab', 'report')
            ->assertSee('Management Inventory &amp; Lifecycle Report', false)
            ->call('setTab', 'assets')
            ->assertSet('currentTab', 'assets');
    }

    public function test_instant_search_and_clear_filters(): void
    {
        $assetAlpha = Asset::create([
            'asset_tag' => 'CBVH-ALPHA-999',
            'name' => 'Alpha Graphic Workstation',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->dept->id,
            'ownership_type' => 'shared',
            'status' => 'in_use',
            'brand' => 'Apple',
            'model' => 'Mac Studio M2',
        ]);

        $assetBeta = Asset::create([
            'asset_tag' => 'CBVH-BETA-888',
            'name' => 'Beta Branch Display',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->dept->id,
            'ownership_type' => 'shared',
            'status' => 'in_use',
            'brand' => 'Samsung',
            'model' => 'QLED 65',
        ]);

        Livewire::actingAs($this->techUser)
            ->test(AssetInventoryBoard::class)
            ->set('search', 'ALPHA')
            ->assertSee('CBVH-ALPHA-999')
            ->assertDontSee('CBVH-BETA-888')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSee('CBVH-ALPHA-999')
            ->assertSee('CBVH-BETA-888');
    }

    public function test_lifecycle_filter_eod_and_eol(): void
    {
        $eodAsset = Asset::create([
            'asset_tag' => 'CBVH-EOD-001',
            'name' => 'Legacy Reception PC',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->dept->id,
            'ownership_type' => 'shared',
            'status' => 'in_use',
            'eod_date' => now()->subDay()->toDateString(),
        ]);

        $newAsset = Asset::create([
            'asset_tag' => 'CBVH-NEW-002',
            'name' => 'Brand New Laptop',
            'category_id' => $this->category->id,
            'branch_id' => $this->branch->id,
            'department_id' => $this->dept->id,
            'ownership_type' => 'shared',
            'status' => 'in_use',
            'eod_date' => now()->addYears(2)->toDateString(),
        ]);

        Livewire::actingAs($this->techUser)
            ->test(AssetInventoryBoard::class)
            ->set('lifecycle', 'past_eod')
            ->assertSee('CBVH-EOD-001')
            ->assertDontSee('CBVH-NEW-002');
    }

    public function test_spare_components_search_and_type_filter(): void
    {
        AssetComponent::create([
            'component_type' => 'ram',
            'brand_model' => 'Crucial Ballistix RGB 32GB',
            'capacity_or_specs' => 'DDR4 3600MHz',
            'serial_number' => 'RAM-SN-001',
            'status' => 'in_stock',
            'branch_id' => $this->branch->id,
        ]);

        AssetComponent::create([
            'component_type' => 'ssd',
            'brand_model' => 'Crucial P3 Plus NVMe 1TB',
            'capacity_or_specs' => 'Gen4 x4 M.2',
            'serial_number' => 'SSD-SN-002',
            'status' => 'in_stock',
            'branch_id' => $this->branch->id,
        ]);

        Livewire::actingAs($this->techUser)
            ->test(AssetInventoryBoard::class, ['currentTab' => 'components'])
            ->set('compSearch', 'Ballistix')
            ->assertSee('Crucial Ballistix RGB')
            ->assertDontSee('Crucial P3 Plus')
            ->call('clearCompFilters')
            ->assertSee('Crucial Ballistix RGB')
            ->assertSee('Crucial P3 Plus')
            ->set('compType', 'ssd')
            ->assertSee('Crucial P3 Plus')
            ->assertDontSee('Crucial Ballistix RGB');
    }

    public function test_software_license_financial_masking_by_role(): void
    {
        SoftwareLicense::create([
            'name' => 'AutoCAD Architecture 2026',
            'vendor_publisher' => 'Autodesk',
            'license_type' => 'subscription',
            'seats_total' => 5,
            'purchase_cost' => 4500.00,
            'status' => 'active',
        ]);

        // Tech sees masked confidential label
        Livewire::actingAs($this->techUser)
            ->test(AssetInventoryBoard::class, ['currentTab' => 'licenses'])
            ->assertSee('AutoCAD Architecture 2026')
            ->assertSee('[Management Confidential]');

        // Admin sees actual financial figures
        Livewire::actingAs($this->adminUser)
            ->test(AssetInventoryBoard::class, ['currentTab' => 'licenses'])
            ->assertSee('AutoCAD Architecture 2026')
            ->assertSee('4,500.00');
    }
}
