<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_workspace_pages_render_with_empty_data(): void
    {
        // No external service calls are allowed by the isolated TestCase.
        $paths = [
            'learner' => ['/my-courses', '/courses', '/practice', '/mock-exams', '/analytics', '/my-cohorts', '/internship', '/simulations', '/pathways', '/community', '/events', '/certificates', '/portfolio', '/bookmarks', '/downloads', '/storefront', '/orders', '/subscriptions', '/profile/edit', '/notifications', '/messages'],
            'platform_admin' => ['/admin', '/admin/courses', '/admin/course-approvals', '/admin/questions', '/admin/quizzes', '/admin/mock-exams', '/admin/categories', '/admin/internships', '/admin/simulations', '/admin/users', '/admin/reports', '/admin/events', '/admin/billing', '/admin/subscriptions/plans', '/admin/erp', '/admin/erp/contacts', '/admin/erp/deals', '/admin/erp/messaging', '/admin/audit-logs', '/admin/settings', '/admin/launch-checklist'],
            'facilitator' => ['/facilitator', '/facilitator/courses', '/facilitator/cohorts', '/facilitator/grading', '/facilitator/ai-studio', '/facilitator/internship-oversight/health', '/facilitator/internship-oversight/portfolios'],
            'sme_reviewer' => ['/sme/dashboard', '/sme', '/sme/pending', '/sme/questions', '/sme/flags', '/sme/questions/analytics-dashboard', '/sme/questions/blueprint-coverage'],
            'sponsor' => ['/sponsor', '/sponsor/review-queue', '/sponsor/interns', '/sponsor/scorecards', '/sponsor/meetings'],
        ];
        $failures = [];
        foreach ($paths as $role => $urls) {
            Role::findOrCreate($role, 'web');
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user);
            foreach ($urls as $url) {
                $response = $this->get($url);
                if ($response->status() !== 200) {
                    $failures[] = "$role $url: HTTP {$response->status()}";
                }
            }
        }
        $this->assertSame([], $failures, implode("\n", $failures));
    }
}
