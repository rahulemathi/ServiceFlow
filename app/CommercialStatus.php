<?php

namespace App;

enum CommercialStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Active = 'active';
    case Paused = 'paused';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Overdue = 'overdue';
    case Void = 'void';
}
