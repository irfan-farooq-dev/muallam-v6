<?php

namespace App\Enums;

/**
 * What a notification is about. Stored in the notification's data as "kind".
 */
enum NotificationKind: string
{
    case PostLiked = 'post_liked';
    case PostCommented = 'post_commented';
    case CommentReplied = 'comment_replied';
    case PostEditedByAdmin = 'post_edited_by_admin';
    case PostUnpublishedByAdmin = 'post_unpublished_by_admin';
    case PostDeletedByAdmin = 'post_deleted_by_admin';
    case CommentHiddenByAdmin = 'comment_hidden_by_admin';
    case CommentDeletedByAdmin = 'comment_deleted_by_admin';
    case MemberRegistered = 'member_registered';
    case PostPublished = 'post_published';
    case NewComment = 'new_comment';

    /**
     * Bootstrap Icons name (public site); the admin panel maps it to Boxicons.
     */
    public function icon(): string
    {
        return match ($this) {
            self::PostLiked => 'bi-hand-thumbs-up',
            self::PostCommented, self::NewComment => 'bi-chat',
            self::CommentReplied => 'bi-reply',
            self::PostEditedByAdmin => 'bi-pencil-square',
            self::PostUnpublishedByAdmin => 'bi-eye-slash',
            self::PostDeletedByAdmin, self::CommentDeletedByAdmin => 'bi-trash',
            self::CommentHiddenByAdmin => 'bi-chat-square-dots',
            self::MemberRegistered => 'bi-person-plus',
            self::PostPublished => 'bi-journal-check',
        };
    }

    public function adminIcon(): string
    {
        return match ($this) {
            self::PostLiked => 'bx-like',
            self::PostCommented, self::NewComment => 'bx-comment',
            self::CommentReplied => 'bx-reply',
            self::PostEditedByAdmin => 'bx-edit',
            self::PostUnpublishedByAdmin => 'bx-hide',
            self::PostDeletedByAdmin, self::CommentDeletedByAdmin => 'bx-trash',
            self::CommentHiddenByAdmin => 'bx-comment-x',
            self::MemberRegistered => 'bx-user-plus',
            self::PostPublished => 'bx-news',
        };
    }
}
