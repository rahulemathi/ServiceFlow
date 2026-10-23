<?php

namespace App;

enum ServicePriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
