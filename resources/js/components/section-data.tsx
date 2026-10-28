import type { JSXElementConstructor, ReactElement, ReactNode, SubmitEventHandler } from 'react';
import { Field, FieldError, FieldGroup } from './ui/field';
import { Input } from './ui/input';
import { useListingSearch } from '@/hooks/use-listing-search';
import { HugeiconsIcon } from '@hugeicons/react';
import { Archive01Icon, Cancel01Icon, PlusIcon, SearchIcon } from '@hugeicons/core-free-icons';
import { Button } from './ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from './ui/tooltip';
import { ButtonState, ComponentRenderFn, HTMLProps } from '@base-ui/react';
import { Link } from '@inertiajs/react';

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
    filterAction?: ReactNode;
    archiveUrl?: string;
};

export function SectionData({
    dataTable,
    renderButton,
    textButton,
    searchUrl,
    defaultKeyword = '',
    isNativeButton = false,
    filterAction,
    archiveUrl,
}: SectionDataProps) {
    const search = useListingSearch(searchUrl, defaultKeyword);
    const handleSubmit: SubmitEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();
        search.submit();
    };
    const hasKeyword = search.keyword !== '';

    return (
        <section className="container mx-auto flex flex-col gap-6">
            <div className="flex flex-wrap items-start gap-4">
                <div className="flex min-w-0 flex-1 basis-full items-start gap-4 sm:basis-0">
                    {archiveUrl && (
                        <Tooltip>
                            <Button
                                variant="dark"
                                size="icon"
                                nativeButton={false}
                                render={<TooltipTrigger render={<Link href={archiveUrl} />} />}
                                aria-label="Arsip"
                            >
                                <HugeiconsIcon icon={Archive01Icon} strokeWidth={2} />
                            </Button>
                            <TooltipContent>Arsip</TooltipContent>
                        </Tooltip>
                    )}
                    <form onSubmit={handleSubmit} noValidate className="w-full min-w-0 flex-1">
                        <FieldGroup>
                            <Field data-invalid={!!search.error}>
                                <div className="relative flex items-center">
                                    <span className="absolute left-2 text-sm text-muted-foreground">Cari data :</span>
                                    <Input
                                        type="text"
                                        className="w-full ps-20 pe-17 align-middle text-sm placeholder:text-sm"
                                        placeholder="Masukkan kata kunci (min. 2 karakter)..."
                                        autoComplete="off"
                                        tabIndex={1}
                                        id="keyword"
                                        name="keyword"
                                        aria-label="Cari data"
                                        aria-invalid={!!search.error}
                                        aria-describedby={search.error ? 'keyword-error' : undefined}
                                        value={search.keyword}
                                        onChange={(event) => search.setKeyword(event.target.value)}
                                    />

                                    {hasKeyword && (
                                        <Tooltip>
                                            <TooltipTrigger
                                                render={
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon-xs"
                                                        onClick={search.reset}
                                                        aria-label="Hapus pencarian"
                                                        className="absolute right-10"
                                                    />
                                                }
                                            >
                                                <HugeiconsIcon icon={Cancel01Icon} strokeWidth={2} />
                                            </TooltipTrigger>
                                            <TooltipContent>Hapus pencarian</TooltipContent>
                                        </Tooltip>
                                    )}

                                    <Tooltip>
                                        <TooltipTrigger
                                            render={
                                                <Button
                                                    type="submit"
                                                    size="xs"
                                                    aria-label="Cari"
                                                    className="absolute right-1"
                                                />
                                            }
                                        >
                                            <HugeiconsIcon icon={SearchIcon} strokeWidth={2} />
                                        </TooltipTrigger>
                                        <TooltipContent>Cari</TooltipContent>
                                    </Tooltip>
                                </div>
                                {search.error && (
                                    <FieldError id="keyword-error" className="text-xs">
                                        {search.error}
                                    </FieldError>
                                )}
                            </Field>
                        </FieldGroup>
                    </form>
                </div>

                {filterAction}

                {renderButton && (
                    <Button render={renderButton} nativeButton={isNativeButton}>
                        <HugeiconsIcon icon={PlusIcon} strokeWidth={2} />
                        {textButton ?? 'Tambah Data'}
                    </Button>
                )}
            </div>
            {dataTable}
        </section>
    );
}
