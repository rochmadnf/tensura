import { HugeiconsIcon } from '@hugeicons/react';
import { IdVerifiedIcon } from '@hugeicons/core-free-icons';
import { Frame, FramePanel } from '../reui/frame';
import { IconTile } from '../reui/icon-tile';

export function Pattern() {
    return (
        <Frame className="w-fit">
            <FramePanel className="flex flex-row items-center justify-center gap-3">
                <IconTile variant="elevated" size="lg" className="text-emerald-500" aria-hidden="true">
                    <HugeiconsIcon icon={IdVerifiedIcon} strokeWidth={2} />
                </IconTile>
                <div className="5 space-y-0">
                    <div className="text-xl font-bold tracking-tight text-foreground">17</div>
                    <p className="text-xs">Berhasil</p>
                </div>
            </FramePanel>
        </Frame>
    );
}
