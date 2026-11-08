<?php

namespace App\Enums;

/**
 * Subjects offered on the contact form (the same choices as the old muallam.com form).
 */
enum ContactSubject: string
{
    case GeneralFeedback = 'general_feedback';
    case Suggestion = 'suggestion';
    case Complaint = 'complaint';
    case Marketing = 'marketing';
    case TechnicalIssue = 'technical_issue';

    public function label(): string
    {
        return match ($this) {
            self::GeneralFeedback => 'General feedback',
            self::Suggestion => 'Suggestion',
            self::Complaint => 'Complaint',
            self::Marketing => 'Marketing',
            self::TechnicalIssue => 'Technical issue',
        };
    }
}
