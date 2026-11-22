<?php

namespace App\Notifications;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseRejected extends Notification
{
    use Queueable;

    public function __construct(public Course $course, public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Course Review Update: {$this->course->title}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your course **\"{$this->course->title}\"** requires revisions before it can be published.")
            ->line("**Reason:** {$this->reason}")
            ->action('Edit Course', route('facilitator.courses.edit', $this->course))
            ->line('Please make the requested changes and resubmit for review.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'course_rejected',
            'course_id' => $this->course->id,
            'title' => $this->course->title,
            'reason' => $this->reason,
            'message' => "Your course \"{$this->course->title}\" needs revisions.",
        ];
    }
}
