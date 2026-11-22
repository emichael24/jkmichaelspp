<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Services\CurriculumRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CoursePrerequisiteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('platform_admin', 'web'));
        $this->actingAs($user);

        return $user;
    }

    public function test_graph_rejects_self_cycles_and_unpublished_dependencies_without_partial_writes(): void
    {
        $this->admin();
        [$a, $b, $c] = Course::factory()->count(3)->create(['is_published' => true])->all();
        $draft = Course::factory()->create(['is_published' => false]);
        $this->put('/admin/learning-rules/'.$a->id, ['prerequisite_ids' => [$b->id]])->assertSessionHasNoErrors();
        $this->put('/admin/learning-rules/'.$b->id, ['prerequisite_ids' => [$c->id]])->assertSessionHasNoErrors();
        $this->put('/admin/learning-rules/'.$c->id, ['prerequisite_ids' => [$a->id]])->assertSessionHasErrors('prerequisite_ids');
        $this->put('/admin/learning-rules/'.$c->id, ['prerequisite_ids' => [$c->id]])->assertSessionHasErrors('prerequisite_ids');
        $this->put('/admin/learning-rules/'.$a->id, ['prerequisite_ids' => [$draft->id]])->assertSessionHasErrors('prerequisite_ids');
        $this->assertDatabaseCount('course_prerequisites', 2);
        $this->assertDatabaseHas('course_prerequisites', ['course_id' => $a->id, 'prerequisite_id' => $b->id]);
        $copy = app(CurriculumRevisionService::class)->draft($a, auth()->user());
        $this->assertDatabaseHas('course_prerequisites', ['course_id' => $copy->id, 'prerequisite_id' => $b->id]);
        $this->get('/admin/learning-rules?search='.$a->title)->assertOk();
    }

    public function test_direct_lesson_and_ai_routes_cannot_bypass_prerequisites_or_spoof_another_users_completion(): void
    {
        $this->admin();
        $foundation = Course::factory()->create(['is_published' => true]);
        $advanced = Course::factory()->create(['is_published' => true, 'price' => 0]);
        $module = $advanced->modules()->create(['title' => 'Advanced', 'is_published' => true]);
        $lesson = $module->lessons()->create(['title' => 'Protected lesson', 'content_type' => 'text', 'is_published' => true]);
        $this->put('/admin/learning-rules/'.$advanced->id, ['prerequisite_ids' => [$foundation->id]])->assertSessionHasNoErrors();
        $learner = User::factory()->create();
        $another = User::factory()->create();
        $foundation->enrollments()->create(['user_id' => $another->id, 'status' => 'completed', 'enrolled_at' => now(), 'completed_at' => now()]);
        $this->actingAs($learner)->post('/courses/'.$advanced->id.'/enroll')->assertForbidden();
        $advanced->enrollments()->create(['user_id' => $learner->id, 'status' => 'active', 'enrolled_at' => now()]);
        $this->get('/courses/'.$advanced->slug.'/lessons/'.$lesson->id)->assertForbidden();
        $this->postJson('/ai/tutor/sessions', ['context_type' => 'course', 'context_id' => $advanced->id])->assertForbidden();
        $this->get('/courses/'.$advanced->slug)->assertInertia(fn (Assert $p) => $p->where('prerequisiteCourses.0.completed', false)->where('modules.0.lessons.0.can_access', false));
        $foundation->enrollments()->create(['user_id' => $learner->id, 'status' => 'completed', 'enrolled_at' => now(), 'completed_at' => now()]);
        $this->get('/courses/'.$advanced->slug.'/lessons/'.$lesson->id)->assertOk();
        $this->postJson('/ai/tutor/sessions', ['context_type' => 'course', 'context_id' => $advanced->id])->assertOk();
        $this->putJson('/admin/learning-rules/'.$advanced->id, ['prerequisite_ids' => []])->assertForbidden();
        $this->assertSame(1, DB::table('course_prerequisites')->count());
    }
}
