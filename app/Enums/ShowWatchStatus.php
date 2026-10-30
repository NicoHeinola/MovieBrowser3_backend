<?php

namespace App\Enums;

enum ShowWatchStatus: string
{
    case Watching = 'watching';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Dropped = 'dropped';
    case PlanToWatch = 'plan_to_watch';
}
