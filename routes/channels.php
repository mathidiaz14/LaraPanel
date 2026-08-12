<?php

use App\Models\TerminalSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Interactive Terminal channels
|--------------------------------------------------------------------------
|
| Clients subscribe to `private-terminal.{uuid}`. Laravel's Pusher
| conventions strip the `private-` prefix before matching, so the pattern
| below is registered WITHOUT the prefix, exactly like `App.Models.User.{id}`.
| Access requires a matching, still-open TerminalSession created by the
| authenticated admin through POST /terminal/session.
|
*/

Broadcast::channel('terminal.{channel}', function ($user, $channel) {
    return TerminalSession::canJoin($user, $channel);
});
