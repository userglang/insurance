<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            CREATE TRIGGER check_unique_active_member_before_insert
            BEFORE INSERT ON members
            FOR EACH ROW
            BEGIN
                IF NEW.is_active = 1 THEN
                    IF EXISTS (
                        SELECT 1 FROM members
                        WHERE is_active = 1
                        AND first_name = NEW.first_name
                        AND middle_name = NEW.middle_name
                        AND last_name = NEW.last_name
                        AND birth_date = NEW.birth_date
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Duplicate active member with same name and birthdate';
                    END IF;
                END IF;
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER check_unique_active_member_before_update
            BEFORE UPDATE ON members
            FOR EACH ROW
            BEGIN
                IF NEW.is_active = 1 THEN
                    IF EXISTS (
                        SELECT 1 FROM members
                        WHERE is_active = 1
                        AND first_name = NEW.first_name
                        AND middle_name = NEW.middle_name
                        AND last_name = NEW.last_name
                        AND birth_date = NEW.birth_date
                        AND id != NEW.id
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Duplicate active member with same name and birthdate';
                    END IF;
                END IF;
            END;
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS check_unique_active_member_before_insert;");
        DB::unprepared("DROP TRIGGER IF EXISTS check_unique_active_member_before_update;");
    }
};
