<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $contactPhone = DB::table('site_settings')
            ->where('key', 'contact_phone')
            ->value('value') ?: '+1 437 000 0000';

        $whatsappQuery = DB::table('site_settings')->where('key', 'whatsapp_number');
        $whatsappExists = $whatsappQuery->exists();
        $whatsapp = $whatsappExists ? $whatsappQuery->value('value') : null;

        if (!$whatsappExists) {
            DB::table('site_settings')->insert([
                'key' => 'whatsapp_number',
                'value' => $contactPhone,
                'group_name' => 'contact',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return;
        }

        if (trim((string) $whatsapp) === '') {
            DB::table('site_settings')
                ->where('key', 'whatsapp_number')
                ->update([
                    'value' => $contactPhone,
                    'group_name' => 'contact',
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        //
    }
};
