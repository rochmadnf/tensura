import { useEffect, type ChangeEvent, type DragEvent } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import { Delete02Icon, Upload04Icon, Xls01Icon } from '@hugeicons/core-free-icons';
import { cn } from 'cn';
import { Button } from '@/components/ui/button';
import { formatBytes, useFileUpload } from '@/hooks/use-file-upload';

interface EmployeeExcelUploadProps {
    disabled: boolean;
    invalid: boolean;
    onChange: (file: File | null) => void;
    onError: (message: string) => void;
}

export function EmployeeExcelUpload({ disabled, invalid, onChange, onError }: EmployeeExcelUploadProps) {
    const [{ files, isDragging }, actions] = useFileUpload({
        accept: '.xlsx',
        maxSize: 2 * 1024 * 1024,
        multiple: false,
        maxFiles: 1,
        onFilesAdded: ([item]) => {
            if (item.file instanceof File) onChange(item.file);
        },
    });
    const selected = files[0];

    useEffect(
        () => () => {
            for (const item of files) {
                if (item.preview && item.file instanceof File) URL.revokeObjectURL(item.preview);
            }
        },
        [files],
    );

    function selectFiles(chosen: File[]) {
        if (disabled || chosen.length === 0) return;
        if (chosen.length !== 1) {
            onError('Pilih satu file Excel saja. Beberapa file tidak dapat diunggah sekaligus.');
            return;
        }
        if (!chosen[0].name.toLowerCase().endsWith('.xlsx')) {
            onError('Gunakan file Excel berformat .xlsx.');
            return;
        }
        if (chosen[0].size > 2 * 1024 * 1024) {
            onError('Ukuran file maksimal 2 MB.');
            return;
        }
        actions.addFiles(chosen);
    }

    function handleChange(event: ChangeEvent<HTMLInputElement>) {
        selectFiles(Array.from(event.target.files ?? []));
        event.target.value = '';
    }

    function handleDrop(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();
        event.stopPropagation();
        actions.handleDragLeave(event);
        selectFiles(Array.from(event.dataTransfer.files));
    }

    return (
        <div
            role="group"
            aria-labelledby="employee-file-label"
            aria-disabled={disabled}
            data-dragging={!disabled && isDragging}
            className={cn(
                'flex min-w-0 flex-col gap-4 rounded-lg border border-dashed border-input p-5 sm:p-6',
                !disabled && isDragging && 'border-primary bg-muted',
                disabled && 'opacity-60',
            )}
            onDragEnter={(event) => {
                if (!disabled) actions.handleDragEnter(event);
            }}
            onDragLeave={actions.handleDragLeave}
            onDragOver={actions.handleDragOver}
            onDrop={handleDrop}
        >
            <input
                {...actions.getInputProps({
                    id: 'employee-file',
                    name: 'file',
                    disabled,
                    'aria-invalid': invalid,
                    'aria-describedby': 'employee-file-help employee-file-error',
                })}
                className="sr-only"
                onChange={handleChange}
            />
            <div className="flex flex-wrap items-center gap-4">
                <HugeiconsIcon icon={Xls01Icon} className="size-8 shrink-0 text-muted-foreground" aria-hidden="true" />
                <div className="flex min-w-0 flex-1 flex-col gap-1">
                    <p className="text-sm font-medium wrap-anywhere">
                        {selected ? selected.file.name : 'Seret file Excel ke sini'}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {selected
                            ? formatBytes(selected.file.size)
                            : 'Atau pilih satu file .xlsx dari perangkat Anda. Maksimal 2 MB.'}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={disabled}
                        onClick={() => {
                            if (!disabled) actions.openFileDialog();
                        }}
                    >
                        <HugeiconsIcon icon={Upload04Icon} data-icon="inline-start" aria-hidden="true" />
                        {selected ? 'Ganti File' : 'Pilih File Excel'}
                    </Button>
                    {selected && (
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={disabled}
                            onClick={() => {
                                if (disabled) return;
                                actions.clearFiles();
                                onChange(null);
                            }}
                        >
                            <HugeiconsIcon icon={Delete02Icon} data-icon="inline-start" aria-hidden="true" />
                            Hapus File
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
