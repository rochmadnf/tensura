import { Edit04Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { CardAction } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

export function TemplateEditAction({
    label,
    disabled,
    onClick,
}: {
    label: string;
    disabled: boolean;
    onClick: () => void;
}) {
    return (
        <CardAction>
            <Button
                type="button"
                size="sm"
                variant="warning"
                disabled={disabled}
                onClick={onClick}
                aria-label={`Edit ${label}`}
            >
                <HugeiconsIcon icon={Edit04Icon} data-icon="inline-start" aria-hidden="true" />
                Edit
            </Button>
        </CardAction>
    );
}
