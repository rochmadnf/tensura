import { useEffect, useEffectEvent, useRef } from 'react';
import type { JSXElementConstructor, ReactElement, ReactNode, SubmitEventHandler } from 'react';
import { useDebounceValue } from 'usehooks-ts';
import { Field, FieldError, FieldGroup } from './ui/field';
import { Input } from './ui/input';
import { useValibotForm } from '@/hooks/use-valibot-form';
import { InputSearchSchema } from '@/schemas/input-search';
import { HugeiconsIcon } from '@hugeicons/react';
import { Cancel01Icon, PlusIcon, SearchIcon } from '@hugeicons/core-free-icons';
import { Button } from './ui/button';
import { ButtonState, ComponentRenderFn, HTMLProps } from '@base-ui/react';
import { router } from '@inertiajs/react';

const DEBOUNCE_MS = 500;
const MIN_KEYWORD_LENGTH = 2; // samakan dengan minLength(2) di InputSearchSchema

export type SectionDataProps = {
    renderButton?:
        | ReactElement<unknown, string | JSXElementConstructor<any>>
        | ComponentRenderFn<HTMLProps, ButtonState>
        | undefined;
    textButton?: string | undefined;
    isNativeButton?: boolean;
    dataTable: ReactNode;
    searchUrl: string;
    defaultKeyword?: string;
};

export function SectionData({
    dataTable,
    renderButton,
    textButton,
    searchUrl,
    defaultKeyword = '',
    isNativeButton = false,
}: SectionDataProps) {
    const { form, bind } = useValibotForm(InputSearchSchema, {
        keyword: defaultKeyword,
    });

    const appliedKeyword = useRef(defaultKeyword.trim());

    const visit = (keyword: string) => {
        appliedKeyword.current = keyword;

        const params = new URLSearchParams(window.location.search);
        params.delete('page');
        params.delete('keyword');
        if (keyword) params.set('keyword', keyword);

        router.get(searchUrl, Object.fromEntries(params), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['resources', 'filters'],
        });
    };

    // validasi dengan InputSearchSchema, lalu kirim jika valid
    const search = (raw: string) => {
        const result = InputSearchSchema.validate({ keyword: raw });

        if (!result.success) {
            form.setError('keyword', result.errors.keyword?.[0] ?? 'Kata kunci tidak valid.');
            return;
        }

        form.clearErrors('keyword');
        visit(result.data.keyword);
    };

    // opsi 1: tombol cari / Enter
    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();
        search(form.data.keyword);
    };

    // tombol X
    const handleReset = () => {
        form.setData('keyword', '');
        form.clearErrors('keyword');

        if (appliedKeyword.current !== '') visit('');
    };

    const [debouncedKeyword] = useDebounceValue(form.data.keyword, DEBOUNCE_MS);

    const onDebouncedKeyword = useEffectEvent((raw: string) => {
        const keyword = raw.trim();

        if (keyword === appliedKeyword.current) return;

        if (keyword === '') {
            form.clearErrors('keyword');
            visit('');
            return;
        }

        if (keyword.length < MIN_KEYWORD_LENGTH) return;

        search(keyword);
    });

    useEffect(() => {
        onDebouncedKeyword(debouncedKeyword);
    }, [debouncedKeyword]);

    const hasKeyword = form.data.keyword !== '';

    return (
        <section className="container mx-auto space-y-6">
            <div className="flex flex-row gap-4">
                <form onSubmit={handleSubmit} noValidate className="w-full flex-1">
                    <FieldGroup>
                        <Field data-invalid={!!form.errors.keyword}>
                            <div className="relative flex items-center">
                                <span className="absolute left-2 text-sm text-muted-foreground">Cari data :</span>
                                <Input
                                    type="text"
                                    className="w-full ps-20 pe-17 align-middle text-sm placeholder:text-sm"
                                    placeholder="Masukkan kata kunci (min. 2 karakter)..."
                                    autoComplete="off"
                                    tabIndex={1}
                                    {...bind('keyword')}
                                />

                                {hasKeyword && (
                                    <button
                                        type="button"
                                        onClick={handleReset}
                                        aria-label="Hapus pencarian"
                                        className="absolute right-10 inline-flex size-6 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    >
                                        <HugeiconsIcon icon={Cancel01Icon} strokeWidth={2} size={14} />
                                    </button>
                                )}

                                <button
                                    type="submit"
                                    aria-label="Cari"
                                    className="absolute right-1 inline-flex h-6.5 items-center gap-x-2 rounded-sm bg-primary px-2 py-1 text-white transition-all duration-200 hover:bg-primary/90"
                                >
                                    <HugeiconsIcon icon={SearchIcon} strokeWidth={2} size={16} />
                                </button>
                            </div>
                            {form.errors.keyword && <FieldError className="text-xs">{form.errors.keyword}</FieldError>}
                        </Field>
                    </FieldGroup>
                </form>

                <Button render={renderButton} nativeButton={isNativeButton}>
                    <HugeiconsIcon icon={PlusIcon} strokeWidth={2} />
                    {textButton ?? 'Tambah Data'}
                </Button>
            </div>
            {dataTable}
        </section>
    );
}
