<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->shiftMySqlDateTimes(toAsiaKolkata: true);

        if (Schema::hasTable('events')) {
            DB::table('events')->where('timezone', 'UTC')->update(['timezone' => 'Asia/Kolkata']);
            Schema::table('events', function (Blueprint $table): void {
                $table->string('timezone', 64)->default('Asia/Kolkata')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events')) {
            DB::table('events')->where('timezone', 'Asia/Kolkata')->update(['timezone' => 'UTC']);
            Schema::table('events', function (Blueprint $table): void {
                $table->string('timezone', 64)->default('UTC')->change();
            });
        }

        $this->shiftMySqlDateTimes(toAsiaKolkata: false);
    }

    private function shiftMySqlDateTimes(bool $toAsiaKolkata): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $columns = DB::select(
            <<<'SQL'
                SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ?
                  AND DATA_TYPE = 'datetime'
                  AND EXTRA NOT LIKE '%GENERATED%'
                ORDER BY TABLE_NAME, ORDINAL_POSITION
            SQL,
            [DB::getDatabaseName()],
        );

        foreach ($columns as $column) {
            $table = (string) $column->table_name;
            $name = (string) $column->column_name;
            if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1 || preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
                throw new RuntimeException('Unsafe database identifier encountered during timezone conversion.');
            }

            $expression = $toAsiaKolkata
                ? "DATE_ADD(`{$name}`, INTERVAL 330 MINUTE)"
                : "DATE_SUB(`{$name}`, INTERVAL 330 MINUTE)";
            DB::statement("UPDATE `{$table}` SET `{$name}` = {$expression} WHERE `{$name}` IS NOT NULL");
        }
    }
};
