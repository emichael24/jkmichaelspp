<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Project Management',
                'slug' => 'project-management',
                'description' => 'PMP, PRINCE2, Agile, Scrum and other project management methodologies',
                'icon' => 'clipboard-list',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Business Analysis',
                'slug' => 'business-analysis',
                'description' => 'CBAP, ECBA, requirements engineering, process modeling',
                'icon' => 'bar-chart-2',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'IT Service Management',
                'slug' => 'it-service-management',
                'description' => 'ITIL, service desk, incident management, change management',
                'icon' => 'server',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Leadership & Strategy',
                'slug' => 'leadership-strategy',
                'description' => 'Executive leadership, strategic planning, organizational development',
                'icon' => 'compass',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Risk Management',
                'slug' => 'risk-management',
                'description' => 'PMI-RMP, enterprise risk, compliance, audit frameworks',
                'icon' => 'shield',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Quality Management',
                'slug' => 'quality-management',
                'description' => 'Lean Six Sigma, total quality management, ISO standards',
                'icon' => 'check-circle',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'Procurement & Supply Chain',
                'slug' => 'procurement-supply-chain',
                'description' => 'Procurement planning, vendor management, supply chain optimization',
                'icon' => 'package',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'Professional Development',
                'slug' => 'professional-development',
                'description' => 'Soft skills, communication, time management, career growth',
                'icon' => 'trending-up',
                'sort_order' => 8,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $data) {
            Category::firstOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
