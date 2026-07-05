@props([
    'title',
    'message',
    'actionLabel' => null,
    'actionHref' => null,
])

<x-empty-state :title="$title" :message="$message" :action-label="$actionLabel" :action-href="$actionHref" />
