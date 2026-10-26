import { Edit04Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { CardAction } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

export function TemplateEditAction({
    label,
    disabled,
    onClick,
    inline = false,
}: {
    inline?: boolean;
    label: string;
    disabled: boolean;
    onClick: () => void;
}) {
    const button = (
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
    );
    return inline ? button : <CardAction>{button}</CardAction>;
}
