<?php

namespace App\Enums;

enum ProductRequestItemWorkflowStatus: string
{
    case AddQuotation = 'add_quotation';
    case Pending = 'pending';
    case CreatePo = 'create_po';
    case PoCreated = 'po_created';
    case Dispatch = 'dispatch';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AddQuotation => 'Add Quotation',
            self::Pending => 'Pending',
            self::CreatePo => 'Create PO',
            self::PoCreated => 'PO Created',
            self::Dispatch => 'Dispatch',
            self::Cancelled => 'Cancelled',
        };
    }
}
