<?php

namespace App\Notifications;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseSubmittedForReview extends Notification
{
    use Queueable;

    public function __construct(public Course $course) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Course Pending Review: {$this->course->title}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line('A facilitator has submitted a course for your approval.')
            ->line("**Course:** {$this->course->title}")
            ->line("**Author:** {$this->course->author->full_name}")
            ->action('Review Course', route('admin.course-approvals.show', $this->course))
            ->line('Please review and approve or reject within 48 hours.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'course_submitted_for_review',
            'course_id' => $this->course->id,
            'title' => $this->course->title,
            'author' => $this->course->author->full_name ?? 'Unknown',
            'message' => "New course \"{$this->course->title}\" submitted for review.",
        ];
    }
}
