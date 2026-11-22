<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacyCourseMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private function legacySchema(): void
    {
        Schema::table('courses', fn (Blueprint $table) => $table->string('type', 32)->default('self_paced')->change());
    }

    public function test_legacy_commercial_types_are_preserved_without_changing_money_or_canonical_types(): void
    {
        $this->legacySchema();
        $courses = [];
        foreach (['premium', 'free', 'enterprise', 'live', 'certified', 'bootcamp'] as $type) {
            $course = Course::factory()->create(['type' => $type, 'price' => 15000, 'discount_price' => 12000]);
            $courses[$course->id] = (array) DB::table('courses')->find($course->id);
        }
        $migration = require database_path('migrations/2026_10_05_100000_normalize_legacy_course_classifications.php');
        $migration->up();
        $migration->up();
        foreach ($courses as $id => $before) {
            $after = (array) DB::table('courses')->find($id);
            if (in_array($before['type'], ['premium', 'free', 'enterprise'], true)) {
                $this->assertSame($before['type'], $after['legacy_commercial_type']);
                $this->assertSame('self_paced', $after['type']);
            } else {
                $this->assertSame($before['type'], $after['type']);
                $this->assertNull($after['legacy_commercial_type']);
            }
            unset($before['type'], $before['legacy_commercial_type'], $after['type'], $after['legacy_commercial_type']);
            $this->assertSame($before, $after);
        }
        $resourceId = DB::table('resources')->insertGetId([
            'course_id' => array_key_first($courses), 'title' => 'PDF handout', 'type' => 'template',
            'file_url' => '/private-media/synthetic', 'file_type' => 'application/pdf',
        ]);
        $this->assertDatabaseHas('resources', ['id' => $resourceId, 'file_type' => 'application/pdf']);
    }

    public function test_unknown_classification_fails_without_changing_any_course(): void
    {
        $this->legacySchema();
        $course = Course::factory()->create(['type' => 'unknown']);
        $before = (array) DB::table('courses')->find($course->id);
        $migration = require database_path('migrations/2026_10_05_100000_normalize_legacy_course_classifications.php');
        try {
            $migration->up();
            $this->fail('Unrecognized legacy data must not be guessed.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('requires review', $error->getMessage());
        }
        $this->assertSame($before, (array) DB::table('courses')->find($course->id));
    }
}
