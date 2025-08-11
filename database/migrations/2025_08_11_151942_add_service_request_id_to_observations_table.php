<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->unsignedBigInteger('service_request_id')->nullable()->index()->after('encounter_id');
            $table->float('reference_range_low')->nullable()->after('observed_by');
            $table->float('reference_range_high')->nullable()->after('reference_range_low');
            $table->string('reference_range_text')->nullable()->after('reference_range_high');
            $table->string('interpretation')->nullable()->after('reference_range_text');
            $table->text('comments')->nullable()->after('interpretation');
            $table->datetime('verified_at')->nullable()->after('comments');
            $table->bigInteger('verified_by')->nullable()->after('verified_at');
            $table->boolean('value_boolean')->nullable()->after('value_datetime');
            
            $table->foreign('service_request_id')->references('id')->on('service_requests');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->dropForeign(['service_request_id']);
            $table->dropColumn([
                'service_request_id',
                'reference_range_low',
                'reference_range_high',
                'reference_range_text',
                'interpretation',
                'comments',
                'verified_at',
                'verified_by',
                'value_boolean',
            ]);
        });
    }
};
