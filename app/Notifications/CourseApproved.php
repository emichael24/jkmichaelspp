<?php

namespace App\Notifications;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class CourseApproved extends Notification
{
    use Queueable;

    public function __construct(public Course $course, public ?string $notes = null) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Your Course Has Been Approved: {$this->course->title}")
            ->greeting("Congratulations {$notifiable->first_name}!")
            ->line("Your course **\"{$this->course->title}\"** has been approved and published on the platform.")
            ->action('View Course', route('courses.show', $this->course->slug));

        if ($this->notes) {
            $mail->line("**Admin notes:** {$this->notes}");
        }

        return $mail->line('Thank you for contributing to the JK Michaels platform!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'course_approved',
            'course_id' => $this->course->id,
            'title'     => $this->course->title,
            'message'   => "Your course \"{$this->course->title}\" has been approved!",
        ];
    }
}
