<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->ulid()->index();
            $table->string('code')->index();
            $table->foreignId('card_type_id')->index()->constrained('terms');
            $table->date('issue_date')->index();
            $table->date('expiry_date')->index();
        });
    }

    public function down()
    {
        Schema::dropIfExists('cards');
    }
};
