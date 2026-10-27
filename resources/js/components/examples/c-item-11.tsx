import { Badge } from '@/components/reui/badge';

import { Item, ItemActions, ItemContent, ItemDescription, ItemGroup, ItemMedia, ItemTitle } from '@/components/ui/item';
import { HugeiconsIcon } from '@hugeicons/react';
import { Copy02Icon } from '@hugeicons/core-free-icons';

export function Pattern() {
    return (
        <div className="mx-auto flex w-full max-w-md flex-col">
            <ItemGroup>
                <Item variant="outline" size="xs">
                    <ItemMedia variant="icon">
                        <HugeiconsIcon icon={Copy02Icon} strokeWidth={2} />
                    </ItemMedia>
                    <ItemContent>
                        <ItemTitle>Rochmad Nurul Fahmi</ItemTitle>
                        <ItemDescription>Baris ke-2</ItemDescription>
                    </ItemContent>
                    <ItemActions>
                        <Badge variant="warning-light" size="sm">
                            Duplikat
                        </Badge>
                    </ItemActions>
                </Item>
            </ItemGroup>
        </div>
    );
}
