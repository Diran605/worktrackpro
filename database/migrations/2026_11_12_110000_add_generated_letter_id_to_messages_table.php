<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link letter messages to their letter directly. Acknowledgements used to find the letter by
     * matching the attachment's file path, which breaks if a file is renamed or re-generated.
     * A letter can have several messages (it can be resent), so the link lives on the message.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('generated_letter_id')->nullable()->after('message_type')
                ->constrained('generated_letters')->nullOnDelete();
        });

        // Backfill existing letter messages through their PDF attachment.
        DB::table('generated_letters')->select('id', 'pdf_path')->orderBy('id')->each(function ($letter) {
            $messageIds = DB::table('message_attachments')
                ->where('file_path', $letter->pdf_path)
                ->pluck('message_id');

            DB::table('messages')
                ->whereIn('id', $messageIds)
                ->where('message_type', 'letter')
                ->update(['generated_letter_id' => $letter->id]);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generated_letter_id');
        });
    }
};
