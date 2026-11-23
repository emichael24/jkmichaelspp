<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 40)->default('general');
            $table->string('subject');
            $table->longText('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $app = rtrim((string) config('app.url'), '/');
        $now = now();
        $templates = [
            ['Newsletter', 'newsletter', 'What’s new at JK Michaels this month',
                '<p>Hi {{first_name}},</p><p>Here is a quick round-up of what is happening across the academy this month.</p><h2>New and upcoming courses</h2><ul><li><strong>Course name</strong> – one line on what learners will achieve.</li><li><strong>Course name</strong> – one line on what learners will achieve.</li></ul><h2>From the community</h2><p>Share a learner success story, an exam tip or a short update from your facilitators.</p><p><a href="'.$app.'/courses">Browse all courses</a></p><p>Keep learning,<br>The JK Michaels team</p>'],
            ['New course announcement', 'announcement', '{{first_name}}, a new course is now open',
                '<p>Hi {{first_name}},</p><h2>Introducing: Course name</h2><p>Explain in two sentences who the course is for and the result they can expect.</p><ul><li>Format: self-paced lessons, quizzes and case studies</li><li>Duration: number of weeks</li><li>Certificate on completion</li></ul><p><a href="'.$app.'/courses">See the course and enrol</a></p><p>Questions? Reply to this email and our team will help.</p><p>The JK Michaels team</p>'],
            ['Event or webinar invitation', 'event', 'You’re invited: event name',
                '<p>Hi {{first_name}},</p><p>Join us for <strong>event name</strong>, a live session on topic.</p><table><tr><td><strong>Date</strong></td><td>Day, date</td></tr><tr><td><strong>Time</strong></td><td>Time (WAT)</td></tr><tr><td><strong>Where</strong></td><td>Online / venue</td></tr></table><p><a href="'.$app.'/events">Reserve your place</a></p><p>We look forward to seeing you.<br>The JK Michaels team</p>'],
            ['Follow-up after an enquiry', 'follow_up', 'Following up on your enquiry',
                '<p>Hi {{first_name}},</p><p>Thank you for your interest in JK Michaels. As promised, here is more information about the programme you asked about.</p><ul><li>What the programme covers</li><li>Start dates and schedule</li><li>Fees and payment options</li></ul><p>If it would help, I am happy to arrange a short call to answer any questions.</p><p>Kind regards,<br>Your name<br>JK Michaels</p>'],
            ['Welcome to the academy', 'welcome', 'Welcome to JK Michaels, {{first_name}}',
                '<p>Hi {{first_name}},</p><p>Welcome to JK Michaels. We are glad you are here.</p><h3>Your first steps</h3><ol><li>Complete your profile.</li><li>Open your first course and start lesson one.</li><li>Join the community forum to meet other learners.</li></ol><p><a href="'.$app.'/dashboard">Go to your dashboard</a></p><p>The JK Michaels team</p>'],
            ['Class or exam reminder', 'reminder', 'Reminder: session name is coming up',
                '<p>Hi {{first_name}},</p><p>This is a friendly reminder that <strong>session name</strong> takes place on <strong>day, date at time</strong>.</p><p>Please make sure you have reviewed the preparation materials before the session.</p><p><a href="'.$app.'/dashboard">Open your dashboard</a></p><p>See you there,<br>The JK Michaels team</p>'],
        ];
        DB::table('email_templates')->insert(array_map(fn ($template) => [
            'name' => $template[0], 'category' => $template[1], 'subject' => $template[2], 'body' => $template[3],
            'created_by' => null, 'created_at' => $now, 'updated_at' => $now,
        ], $templates));
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
