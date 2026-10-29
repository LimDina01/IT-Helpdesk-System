<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $defaults = [
            'Network & Internet Connectivity' => [
                'description' => 'Network, Wi-Fi, VPN, internet',
                'description_km' => 'ប្រព័ន្ធបណ្តាញ Wi-Fi, VPN, និងអ៊ីនធឺណិត',
                'icon' => 'wifi',
                'icon_color' => 'emerald',
            ],
            'Software & Applications' => [
                'description' => 'Software installation, license request, application access',
                'description_km' => 'ការដំឡើងកម្មវិធី ស្នើសុំអាជ្ញាប័ណ្ណ ឬការចូលប្រើប្រាស់',
                'icon' => 'apps',
                'icon_color' => 'blue',
            ],
            'Software & Application' => [
                'description' => 'Software installation, license request, application access',
                'description_km' => 'ការដំឡើងកម្មវិធី ស្នើសុំអាជ្ញាប័ណ្ណ ឬការចូលប្រើប្រាស់',
                'icon' => 'apps',
                'icon_color' => 'blue',
            ],
            'Business Systems & POS' => [
                'description' => 'Receipt printer, barcode scanner, cash drawer, terminal',
                'description_km' => 'ម៉ាស៊ីនព្រីនវិក្កយបត្រ ស្កេនបាកូដ ថតលុយ ឬស្ថានីយ POS',
                'icon' => 'pos',
                'icon_color' => 'sky',
            ],
            'Business Systems' => [
                'description' => 'Business Central, POS, RFID, Humica, IQ, Bio-Start',
                'description_km' => 'ប្រព័ន្ធ Business Central, POS, RFID, Humica, IQ, Bio-Start',
                'icon' => 'database',
                'icon_color' => 'blue',
            ],
            'Facility, CCTV & Phone' => [
                'description' => 'Meeting room, CCTV, phone, general services',
                'description_km' => 'បន្ទប់ប្រជុំ កាមេរ៉ាសុវត្ថិភាព ទូរស័ព្ទ និងសេវាទូទៅ',
                'icon' => 'facility',
                'icon_color' => 'amber',
            ],
            'Facility & Other' => [
                'description' => 'Meeting room, CCTV, phone, general services',
                'description_km' => 'បន្ទប់ប្រជុំ កាមេរ៉ាសុវត្ថិភាព ទូរស័ព្ទ និងសេវាទូទៅ',
                'icon' => 'facility',
                'icon_color' => 'amber',
            ],
            'Account & Access Requests' => [
                'description' => 'New account, system access, permission change',
                'description_km' => 'បង្កើតគណនីថ្មី សិទ្ធិចូលប្រព័ន្ធ ឬប្តូរសិទ្ធិប្រើប្រាស់',
                'icon' => 'lock',
                'icon_color' => 'purple',
            ],
            'Account & Access' => [
                'description' => 'New account, system access, permission change',
                'description_km' => 'បង្កើតគណនីថ្មី សិទ្ធិចូលប្រព័ន្ធ ឬប្តូរសិទ្ធិប្រើប្រាស់',
                'icon' => 'lock',
                'icon_color' => 'purple',
            ],
            'Hardware & Equipment' => [
                'description' => 'Laptop, desktop, printer, network device, accessories',
                'description_km' => 'កុំព្យូទ័រយួរដៃ លើតុ ម៉ាស៊ីនព្រីន និងឧបករណ៍បច្ចេកវិទ្យា',
                'icon' => 'laptop',
                'icon_color' => 'sky',
            ],
            'Email & Collaboration' => [
                'description' => 'Email, Microsoft 365, Teams, SharePoint',
                'description_km' => 'អ៊ីមែល Microsoft 365, Teams, SharePoint',
                'icon' => 'email',
                'icon_color' => 'rose',
            ],
            'Service Requests & Procurement' => [
                'description' => 'Hardware procurement, IT asset spec recommendation',
                'description_km' => 'ស្នើសុំកុំព្យូទ័រថ្មី បរិក្ខារ IT និងការបញ្ជាក់លក្ខណៈបច្ចេកទេស',
                'icon' => 'cart',
                'icon_color' => 'indigo',
            ],
            'Other' => [
                'description' => 'General inquiries, unlisted issues, other requests',
                'description_km' => 'សំណួរទូទៅ និងសេវាផ្សេងៗដែលមិនមានក្នុងបញ្ជី',
                'icon' => 'other',
                'icon_color' => 'slate',
            ],
        ];

        foreach ($defaults as $name => $values) {
            DB::table('ticket_categories')
                ->where('name', $name)
                ->whereNull('parent_id')
                ->update($values);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
