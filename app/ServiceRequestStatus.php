<?php

namespace App;

enum ServiceRequestStatus: string
{
    case Open = 'open';
    case Reviewing = 'reviewing';
    case Converted = 'converted';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
