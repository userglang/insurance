<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // -------------------------------------------------
        // 1. DROP OLD UNIQUE INDEX
        // -------------------------------------------------
        DB::statement("
            ALTER TABLE members
            DROP INDEX unique_person_full
        ");

        // -------------------------------------------------
        // 2. DROP OLD TRIGGERS (safe reset)
        // -------------------------------------------------
        DB::unprepared("DROP TRIGGER IF EXISTS check_unique_active_member_before_insert");
        DB::unprepared("DROP TRIGGER IF EXISTS check_unique_active_member_before_update");

        // -------------------------------------------------
        // 3. CREATE NEW INSERT TRIGGER
        // -------------------------------------------------
        DB::unprepared("
            CREATE TRIGGER check_unique_active_member_before_insert
            BEFORE INSERT ON members
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM members
                    WHERE first_name = NEW.first_name
                      AND last_name = NEW.last_name
                      AND birth_date = NEW.birth_date
                      AND branch_number = NEW.branch_number
                      AND (
                          middle_name = NEW.middle_name
                          OR (middle_name IS NULL AND NEW.middle_name IS NULL)
                      )
                ) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Duplicate member in the same branch';
                END IF;
            END;
        ");

        // -------------------------------------------------
        // 4. CREATE NEW UPDATE TRIGGER
        // -------------------------------------------------
        DB::unprepared("
            CREATE TRIGGER check_unique_active_member_before_update
            BEFORE UPDATE ON members
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1
                    FROM members
                    WHERE id != NEW.id
                      AND first_name = NEW.first_name
                      AND last_name = NEW.last_name
                      AND birth_date = NEW.birth_date
                      AND branch_number = NEW.branch_number
                      AND (
                          middle_name = NEW.middle_name
                          OR (middle_name IS NULL AND NEW.middle_name IS NULL)
                      )
                ) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Duplicate member in the same branch';
                END IF;
            END;
        ");
    }

    public function down(): void
    {
        // rollback: remove new triggers
        DB::unprepared("DROP TRIGGER IF EXISTS check_unique_active_member_before_insert");
        DB::unprepared("DROP TRIGGER IF EXISTS check_unique_active_member_before_update");

        // (optional) you can recreate old index if needed, but usually skipped
    }
};
