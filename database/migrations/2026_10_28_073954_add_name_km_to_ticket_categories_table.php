<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->string('name_km', 150)->nullable()->after('name');
        });

        // Dictionary of Khmer translations for existing categories and subcategories
        $translations = [
            // Parent Categories
            'POS & Cashier Hardware' => 'ប្រព័ន្ធ POS & ឧបករណ៍គិតលុយ',
            'Hardware & Office Equipment' => 'កុំព្យូទ័រ & ឧបករណ៍ការិយាល័យ',
            'Network & Internet Connectivity' => 'ប្រព័ន្ធបណ្តាញ & អ៊ីនធឺណិត',
            'Software & Applications' => 'កម្មវិធីកុំព្យូទ័រ & ប្រព័ន្ធប្រតិបត្តិការ',
            'Account & Access Requests' => 'ការស្នើសុំគណនី & សិទ្ធិចូលប្រព័ន្ធ',
            'Account & Access' => 'គណនី & សិទ្ធិចូលប្រព័ន្ធ',
            'Hardware & Equipment' => 'កុំព្យូទ័រ & ឧបករណ៍បច្ចេកវិទ្យា',
            'Software & Application' => 'កម្មវិធីកុំព្យូទ័រ & អាជ្ញាប័ណ្ណ',
            'Email & Collaboration' => 'អ៊ីមែល & Microsoft 365',
            'Network & Connectivity' => 'ប្រព័ន្ធបណ្តាញ & ការតភ្ជាប់',
            'Business Systems' => 'ប្រព័ន្ធគ្រប់គ្រងអាជីវកម្ម (ERP)',
            'Facility & Other' => 'សេវា IT ផ្សេងៗ & បន្ទប់ប្រជុំ',
            'Business Systems & POS' => 'ប្រព័ន្ធអាជីវកម្ម & POS',
            'Facility, CCTV & Phone' => 'បន្ទប់ប្រជុំ, CCTV & ទូរស័ព្ទ',
            'Account & Access Issue' => 'បញ្ហាគណនី & សិទ្ធិចូលប្រព័ន្ធ',
            'Email & Collaboration Issue' => 'បញ្ហាអ៊ីមែល & ការទំនាក់ទំនង',
            'Service Requests & Procurement' => 'សំណើស្នើសុំឧបករណ៍ & អាជ្ញាប័ណ្ណ',
            'Facility & Meeting Room Requests' => 'ការស្នើសុំបន្ទប់ប្រជុំ & សេវាទូទៅ',

            // Subcategories - POS & Cashier
            'Thermal Receipt Printer Offline' => 'ម៉ាស៊ីនព្រីនវិក្កយបត្រកម្តៅដាច់ការតភ្ជាប់',
            'Barcode Scanner Not Reading' => 'ម៉ាស៊ីនស្កេនបាកូដមិនដំណើរការ',
            'Cash Drawer Jammed / Not Ejecting' => 'ថតដាក់ប្រាក់គាំង / មិនលោតចេញ',
            'POS Touch Terminal Freezing' => 'អេក្រង់ Touch ម៉ាស៊ីន POS គាំង',
            'POS Cashier Sync Error' => 'បញ្ហាធ្វើសមកាលកម្មទិន្នន័យម៉ាស៊ីន POS',

            // Subcategories - Hardware
            "Desktop PC / Laptop Won't Boot" => 'កុំព្យូទ័រលើតុ / យួរដៃ បើកមិនចេញ',
            'Monitor Blank or Flickering' => 'អេក្រង់ងងឹត ឬញាក់',
            'Office Network Printer Paper Jam' => 'ម៉ាស៊ីនព្រីនបណ្តាញការិយាល័យជាប់ក្រដាស',
            'Mouse / Keyboard Replacement' => 'ប្តូរកណ្តុរ (Mouse) ឬក្តារចុច (Keyboard)',
            'Laptop Procurement' => 'ការស្នើសុំទិញកុំព្យូទ័រយួរដៃ (Laptop)',
            'Desktop Setup' => 'រៀបចំដំឡើងកុំព្យូទ័រលើតុ (Desktop)',
            'Monitor / Display' => 'ស្នើសុំអេក្រង់កុំព្យូទ័រ',
            'Printer / Scanner Request' => 'ស្នើសុំម៉ាស៊ីនព្រីន / ស្កេន',
            'Network Device / Cable' => 'ស្នើសុំឧបករណ៍បណ្តាញ ឬខ្សែបណ្តាញ',
            'Request Extra Monitor / Display' => 'ស្នើសុំបន្ថែមអេក្រង់កុំព្យូទ័រ',
            'Request New Mouse / Keyboard / Cable' => 'ស្នើសុំកណ្តុរ / ក្តារចុច / ខ្សែតភ្ជាប់ថ្មី',
            'Printer Setup on Workstation' => 'ដំឡើងម៉ាស៊ីនព្រីនលើកុំព្យូទ័រការងារ',
            'Office / Desk Relocation (Floor Move)' => 'ការផ្លាស់ប្តូរតុធ្វើការ / ទីតាំងការិយាល័យ',
            'Laptop / Desktop Procurement Request' => 'ការស្នើសុំទិញកុំព្យូទ័រយួរដៃ / លើតុ',
            'Recommend IT Equipment Spec' => 'ការស្នើសុំការណែនាំលក្ខណៈបច្ចេកទេសបរិក្ខារ IT',

            // Subcategories - Network
            'Office Wi-Fi Disconnected' => 'ដាច់ការតភ្ជាប់ Wi-Fi ការិយាល័យ',
            'Ethernet LAN Wall Port Inactive' => 'រន្ធដោតខ្សែ LAN មិនដំណើរការ',
            'VPN Connection Refused' => 'ភ្ជាប់ប្រព័ន្ធ VPN មិនចូល',
            'Branch Main Internet Offline (Critical)' => 'ដាច់អ៊ីនធឺណិតមេនៅសាខា (បន្ទាន់)',
            'Wi-Fi Access / Guest Account' => 'ការចូលប្រើ Wi-Fi / គណនីភ្ញៀវ',
            'VPN Remote Access Request' => 'ការស្នើសុំចូលប្រើ VPN ពីចម្ងាយ',
            'Static IP / Port Allocation' => 'ការស្នើសុំកំណត់លេខ IP ថេរ ឬរន្ធ Port',

            // Subcategories - Software & Apps
            'Outlook / Email Sending Error' => 'បញ្ហាផ្ញើអ៊ីមែល ឬកម្មវិធី Outlook',
            'Accounting / ERP Software Crash' => 'កម្មវិធីគណនេយ្យ / ERP គាំង',
            'Windows OS Activation Error' => 'បញ្ហាអាជ្ញាប័ណ្ណ Windows OS',
            'Microsoft Office License Prompt' => 'ទាមទារអាជ្ញាប័ណ្ណ Microsoft Office',
            'Software Installation' => 'ការដំឡើងកម្មវិធី',
            'License Request' => 'ការស្នើសុំអាជ្ញាប័ណ្ណកម្មវិធី',
            'Application Access' => 'ការស្នើសុំសិទ្ធិចូលកម្មវិធី',
            'ERP / Accounting Setup' => 'ការដំឡើងប្រព័ន្ធ ERP / គណនេយ្យ',

            // Subcategories - Accounts & Access
            'Domain / Windows Password Reset' => 'កំណត់ពាក្យសម្ងាត់ Windows ឡើងវិញ',
            'New Employee Email & Login Creation' => 'បង្កើតអ៊ីមែល និងគណនីបុគ្គលិកថ្មី',
            'Shared Network Drive Folder Access' => 'ស្នើសុំសិទ្ធិចូលថតឯកសារ Shared Folder',
            'Standard Software Installation Request' => 'ស្នើសុំដំឡើងកម្មវិធីស្តង់ដារ',
            'ERP / Business Central Permission Set Request' => 'ស្នើសុំកំណត់សិទ្ធិប្រើប្រាស់ Business Central',
            'New User Account' => 'បង្កើតគណនីអ្នកប្រើប្រាស់ថ្មី',
            'System & Application Access' => 'សិទ្ធិចូលប្រើប្រព័ន្ធ និងកម្មវិធី',
            'Permission / Role Change' => 'ផ្លាស់ប្តូរសិទ្ធិ ឬតួនាទី',
            'Password Reset' => 'កំណត់ពាក្យសម្ងាត់ឡើងវិញ',
            'Account Locked / Password Expired' => 'គណនីជាប់សោ ឬហួសសុពលភាពពាក្យសម្ងាត់',

            // Subcategories - Email & Collaboration
            'Email Account Creation' => 'ការបង្កើតគណនីអ៊ីមែល',
            'Microsoft 365 License' => 'ការស្នើសុំអាជ្ញាប័ណ្ណ Microsoft 365',
            'Teams & SharePoint Access' => 'សិទ្ធិចូលប្រើ Teams និង SharePoint',
            'Shared Mailbox Access' => 'សិទ្ធិចូលប្រើប្រអប់សំបុត្ររួម (Shared Mailbox)',
            'Cannot Send / Receive Email' => 'មិនអាចផ្ញើ ឬទទួលអ៊ីមែលបាន',

            // Subcategories - Business Systems
            'Business Central Access' => 'សិទ្ធិចូលប្រើ Business Central',
            'POS System Setup' => 'ការដំឡើងប្រព័ន្ធលក់ POS',
            'RFID / Scanner Integration' => 'ការភ្ជាប់ប្រព័ន្ធ RFID / ម៉ាស៊ីនស្កេន',
            'Humica HR Access' => 'សិទ្ធិចូលប្រើប្រព័ន្ធ Humica HR',
            'Bio-Start Biometrics' => 'ប្រព័ន្ធស្កេនក្រយៅដៃ Bio-Start',
            'Business Central Error' => 'បញ្ហាប្រព័ន្ធ Business Central',
            'RFID Reader Malfunction' => 'បញ្ហាម៉ាស៊ីនអាន RFID',
            'Humica / Payroll System Down' => 'ប្រព័ន្ធ Humica / បើកប្រាក់ខែដាច់',
            'Business Central (BC) System Error / Timeout' => 'ប្រព័ន្ធ Business Central (BC) មានបញ្ហា / Timeout',
            'BC Posting / Transaction Failed' => 'ប្រតិបត្តិការ BC Posting បរាជ័យ',
            'POS Software Crash / Freeze' => 'កម្មវិធី POS គាំង / មិនដំណើរការ',
            'RFID / Warehouse System Sync Error' => 'បញ្ហា Sync ប្រព័ន្ធ RFID / ឃ្លាំងទំនិញ',
            'Integration / API Synchronization Failure' => 'បញ្ហាភ្ជាប់ API ឬសមកាលកម្មទិន្នន័យ',
            'Power BI / Report Refresh Error' => 'បញ្ហា Refresh របាយការណ៍ Power BI',
            'ERP / Accounting Advanced Permissions' => 'សិទ្ធិកម្រិតខ្ពស់លើប្រព័ន្ធ ERP / គណនេយ្យ',
            'System Feature Enhancement Request' => 'ការស្នើសុំកែលម្អមុខងារប្រព័ន្ធ',

            // Subcategories - Facility & Others
            'Meeting Room AV / Projector' => 'ឧបករណ៍ប្រជុំ / ម៉ាស៊ីនបញ្ចាំងស្លាយ Projector',
            'CCTV Footage Request' => 'ការស្នើសុំឆែកវីដេអូកាមេរ៉ាសុវត្ថិភាព CCTV',
            'Office IP Phone Extension' => 'ការស្នើសុំលេខទូរស័ព្ទ IP ផ្ទៃក្នុង',
            'CCTV Camera Offline' => 'កាមេរ៉ាសុវត្ថិភាព CCTV ដាច់',
            'Desk Phone / Extension Down' => 'ទូរស័ព្ទលើតុ / លេខផ្ទៃក្នុងដាច់',
            'Meeting Room Screen Issue' => 'បញ្ហាអេក្រង់បន្ទប់ប្រជុំ',
            'CCTV Camera Feed Offline / Black Screen' => 'កាមេរ៉ា CCTV ដាច់ / អេក្រង់ខ្មៅ',
            'IP Phone / Telephone Line Not Ringing' => 'ទូរស័ព្ទ IP / ខ្សែទូរស័ព្ទមិនរោទ៍',
            'Meeting Room TV / Screen Projection Issue' => 'បញ្ហាបញ្ចាំងរូបភាពទូរទស្សន៍ / អេក្រង់បន្ទប់ប្រជុំ',
            'Door Access / Electronic Lock Issue' => 'បញ្ហាសោទ្វារអេឡិចត្រូនិច / ស្កេនទ្វារ',
            'Meeting Room IT Setup & Video Conference Support' => 'រៀបចំប្រព័ន្ធ IT បន្ទប់ប្រជុំ និងសន្និសីទវីដេអូ',
            'CCTV Video Recording Review Request' => 'ការស្នើសុំត្រួតពិនិត្យវីដេអូកាមេរ៉ាសុវត្ថិភាព',
            'IP Telephone Extension Request' => 'ការស្នើសុំលេខទូរស័ព្ទ IP ផ្ទៃក្នុងថ្មី',
            'Other' => 'ផ្សេងៗ',
            'Other / Unlisted' => 'ផ្សេងៗ / មិនមានក្នុងបញ្ជី',
        ];

        foreach ($translations as $enName => $kmName) {
            DB::table('ticket_categories')
                ->where('name', $enName)
                ->whereNull('name_km')
                ->update(['name_km' => $kmName]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->dropColumn('name_km');
        });
    }
};
