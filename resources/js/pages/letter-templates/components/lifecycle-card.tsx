import { useRef, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import {
    Archive01Icon,
    Copy01Icon,
    Edit04Icon,
    SentIcon,
    ToggleOffIcon,
    ToggleOnIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';
import { FormCard } from '@/components/form-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FieldError, FieldGroup } from '@/components/ui/field';
import { gooeyToast } from '@/components/ui/goey-toaster';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import lt from '@/routes/lt';
import versions from '@/routes/lt/versions';
import activation from '@/routes/lt/activation';
import type { LetterTemplateLifecycle } from '@/schemas/letter-template-lifecycle';

type VersionOperation = 'publish' | 'draft' | 'archive' | 'activation';
type CommandData = { confirmed: boolean; version_token?: string; test_token?: string; is_active?: boolean };

export function lifecycleOperationText(
    operation: VersionOperation,
    lifecycle: LetterTemplateLifecycle,
    isActive: boolean,
) {
    switch (operation) {
        case 'publish':
            return {
                title: `Publikasikan versi ${lifecycle.number}?`,
                action: 'Publikasikan',
                description:
                    'Versi ini menjadi versi terbit aktif untuk surat baru. Versi terdahulu tetap tersimpan. Status Aktif/Nonaktif templat dipertahankan.',
            };
        case 'archive':
            return {
                title: `Arsipkan draft versi ${lifecycle.number}?`,
                action: 'Arsipkan Draft',
                description:
                    'Draft tidak lagi dapat diedit. Konfigurasi, DOCX, dan riwayat tetap tersimpan pada templat. Ini tidak memindahkan data ke Arsip pusat.',
            };
        case 'draft':
            return lifecycle.draft_mode === 'reopen'
                ? {
                      title: `Ubah versi ${lifecycle.number} menjadi draft?`,
                      action: 'Ubah Versi Ini',
                      description: `Versi belum digunakan sehingga nomor versinya tetap sama. Publikasi serta bukti validasi, uji, dan pemeriksaan visual dibatalkan.${lifecycle.is_current ? ' Templat tidak memiliki versi terbit aktif sampai dipublikasikan kembali.' : ''}`,
                  }
                : {
                      title: `Buat draft baru dari versi ${lifecycle.number}?`,
                      action: lifecycle.status === 3 ? 'Buat Draft dari Arsip' : 'Buat Draft Versi Baru',
                      description:
                          'Konfigurasi dan DOCX disalin ke draft dengan nomor baru. Versi asal tetap utuh; draft baru memerlukan validasi, generate uji, dan pemeriksaan visual sendiri.',
                  };
        case 'activation':
            return isActive
                ? {
                      title: 'Nonaktifkan templat?',
                      action: 'Nonaktifkan Templat',
                      description:
                          'Templat tidak ditawarkan untuk surat baru. Versi, DOCX, dan riwayat tetap tersimpan.',
                  }
                : {
                      title: 'Aktifkan templat?',
                      action: 'Aktifkan Templat',
                      description:
                          'Templat diaktifkan. Penggunaan untuk surat baru tetap memerlukan versi terbit aktif; operasi ini tidak memublikasikan draft.',
                  };
    }
}

export function LetterTemplateLifecycleCard({
    templateId,
    lifecycle,
    testToken,
    isActive,
    disabled,
    onOpen,
    onClose,
    onProcessingChange,
}: {
    templateId: string;
    lifecycle: LetterTemplateLifecycle;
    testToken: string | null;
    isActive: boolean;
    disabled: boolean;
    onOpen: () => void;
    onClose: () => void;
    onProcessingChange: (value: boolean) => void;
}) {
    const [operation, setOperation] = useState<VersionOperation | null>(null);
    const running = useRef(false);
    const form = useForm<CommandData>({ confirmed: true });
    const busy = disabled || form.processing;
    const operationAllowed =
        operation === 'publish'
            ? lifecycle.readiness.ready && !!testToken
            : operation === 'archive'
              ? lifecycle.can_archive
              : operation === 'draft'
                ? lifecycle.can_start_draft
                : true;
    const dialog = operation ? lifecycleOperationText(operation, lifecycle, isActive) : null;
    const args = { letterTemplate: templateId, version: lifecycle.id };
    function open(next: VersionOperation) {
        if (busy) return;
        form.clearErrors();
        setOperation(next);
        onOpen();
    }
    function close() {
        if (running.current) return;
        setOperation(null);
        form.clearErrors();
        onClose();
    }
    function execute() {
        if (busy || running.current || !operation || !operationAllowed) return;
        running.current = true;
        const action = operation === 'activation' ? activation.update(args) : versions[operation](args);
        form.transform(() =>
            operation === 'activation'
                ? { confirmed: true, is_active: !isActive }
                : {
                      confirmed: true,
                      version_token: lifecycle.version_token,
                      ...(operation === 'publish' ? { test_token: testToken! } : {}),
                  },
        );
        form.submit(action.method, action.url, {
            preserveScroll: true,
            showProgress: false,
            onStart: () => onProcessingChange(true),
            onFinish: () => {
                running.current = false;
                onProcessingChange(false);
            },
            onError: (errors) => gooeyToast.error('Galat', { description: Object.values(errors).flat().join(' ') }),
            onSuccess: () => {
                setOperation(null);
                onClose();
                gooeyToast.success('Berhasil', {
                    description:
                        operation === 'publish'
                            ? 'Versi dipublikasikan. Status templat dipertahankan.'
                            : operation === 'draft'
                              ? 'Draft siap dikelola.'
                              : operation === 'archive'
                                ? 'Draft diarsipkan tanpa menghapus riwayat.'
                                : 'Status templat diperbarui.',
                });
            },
        });
    }
    return (
        <FormCard
            sectioned
            title="Publikasi dan Status"
            description={`Kelola versi ${lifecycle.number} serta ketersediaan templat untuk surat baru.`}
        >
            <FieldGroup>
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">
                        {lifecycle.status_label} · Versi {lifecycle.number}
                    </Badge>
                    {lifecycle.is_current && <Badge variant="secondary">Versi terbit aktif</Badge>}
                    <Badge variant={isActive ? 'secondary' : 'outline'}>
                        Templat {isActive ? 'Aktif' : 'Nonaktif'}
                    </Badge>
                </div>
                {lifecycle.editable ? (
                    <ul className="flex flex-col gap-3">
                        {lifecycle.readiness.checks.map((check) => (
                            <li key={check.key} className="flex items-start gap-3">
                                <Badge variant={check.passed ? 'secondary' : 'outline'}>
                                    {check.passed ? 'Siap' : 'Belum'}
                                </Badge>
                                <div className="flex min-w-0 flex-col gap-1">
                                    <p className="font-medium">{check.label}</p>
                                    <p className="text-sm text-muted-foreground">{check.message}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {lifecycle.status === 3
                            ? 'Versi ini diarsipkan. Dokumen dan konfigurasi tetap tersedia untuk diperiksa.'
                            : lifecycle.first_used_at
                              ? 'Versi ini sudah digunakan. Perubahan harus dibuat pada draft versi baru.'
                              : 'Versi ini terbit dan belum digunakan. Ubah menjadi draft untuk menyunting versi yang sama.'}
                    </p>
                )}
                {!isActive && (
                    <p className="text-sm text-muted-foreground">
                        Templat tetap Nonaktif setelah publikasi. Aktifkan secara terpisah ketika siap digunakan.
                    </p>
                )}
                <div className="flex flex-wrap gap-2">
                    {lifecycle.editable && (
                        <Button
                            type="button"
                            disabled={busy || !lifecycle.readiness.ready || !testToken}
                            onClick={() => open('publish')}
                        >
                            <HugeiconsIcon icon={SentIcon} data-icon="inline-start" aria-hidden="true" />
                            Publikasikan
                        </Button>
                    )}
                    {lifecycle.can_start_draft && (
                        <Button type="button" variant="warning" disabled={busy} onClick={() => open('draft')}>
                            <HugeiconsIcon
                                icon={lifecycle.draft_mode === 'reopen' ? Edit04Icon : Copy01Icon}
                                data-icon="inline-start"
                                aria-hidden="true"
                            />
                            {lifecycleOperationText('draft', lifecycle, isActive).action}
                        </Button>
                    )}
                    {lifecycle.draft && lifecycle.draft.id !== lifecycle.id && (
                        <Button
                            nativeButton={false}
                            variant="outline"
                            disabled={busy}
                            render={<Link href={lt.edit(templateId, { query: { version: lifecycle.draft.id } }).url} />}
                        >
                            <HugeiconsIcon icon={Edit04Icon} data-icon="inline-start" aria-hidden="true" />
                            Lanjutkan Draft Versi {lifecycle.draft.number}
                        </Button>
                    )}
                    {lifecycle.can_archive && (
                        <Button type="button" variant="destructive" disabled={busy} onClick={() => open('archive')}>
                            <HugeiconsIcon icon={Archive01Icon} data-icon="inline-start" aria-hidden="true" />
                            Arsipkan Draft
                        </Button>
                    )}
                    <Button
                        type="button"
                        variant={isActive ? 'warning' : 'approve'}
                        disabled={busy}
                        onClick={() => open('activation')}
                    >
                        <HugeiconsIcon
                            icon={isActive ? ToggleOffIcon : ToggleOnIcon}
                            data-icon="inline-start"
                            aria-hidden="true"
                        />
                        {isActive ? 'Nonaktifkan Templat' : 'Aktifkan Templat'}
                    </Button>
                </div>
                <AlertDialog
                    open={operation !== null}
                    onOpenChange={(value) => {
                        if (!value && !busy) close();
                    }}
                >
                    <AlertDialogContent>
                        <AlertDialogTitle>{dialog?.title}</AlertDialogTitle>
                        <AlertDialogDescription>{dialog?.description}</AlertDialogDescription>
                        <FieldError>{Object.values(form.errors).flat().join(' ')}</FieldError>
                        <AlertDialogFooter>
                            <AlertDialogCancel render={<Button type="button" variant="outline" disabled={busy} />}>
                                Batal
                            </AlertDialogCancel>
                            <Button
                                type="button"
                                variant={operation === 'archive' ? 'destructive' : 'default'}
                                disabled={busy || !operationAllowed}
                                onClick={execute}
                            >
                                <HugeiconsIcon
                                    icon={
                                        operation === 'archive'
                                            ? Archive01Icon
                                            : operation === 'publish'
                                              ? SentIcon
                                              : operation === 'draft'
                                                ? Copy01Icon
                                                : isActive
                                                  ? ToggleOffIcon
                                                  : ToggleOnIcon
                                    }
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                                {form.processing ? 'Memproses...' : dialog?.action}
                            </Button>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </FieldGroup>
        </FormCard>
    );
}
