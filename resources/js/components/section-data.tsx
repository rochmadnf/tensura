import { JSXElementConstructor, ReactElement, ReactNode, SubmitEventHandler } from 'react';
import { Field, FieldError, FieldGroup } from './ui/field';
import { Input } from './ui/input';
import { useValibotForm } from '@/hooks/use-valibot-form';
import { InputSearchSchema } from '@/schemas/input-search';
import { HugeiconsIcon } from '@hugeicons/react';
import { PlusIcon, SearchIcon } from '@hugeicons/core-free-icons';
import { Button } from './ui/button';
import { ButtonState, ComponentRenderFn, HTMLProps } from '@base-ui/react';

export type SectionDataProps = {
    renderButton?:
        | ReactElement<unknown, string | JSXElementConstructor<any>>
        | ComponentRenderFn<HTMLProps, ButtonState>
        | undefined;
    textButton?: string | undefined;
    isNativeButton?: boolean;
    dataTable: ReactNode;
};

export function SectionData({ dataTable, renderButton, textButton, isNativeButton = false }: SectionDataProps) {
    const { form, submit, bind } = useValibotForm(InputSearchSchema, {
        keyword: '',
    });

    const handleSearch: SubmitEventHandler<HTMLFormElement> = (e) => {
        e.preventDefault();
        console.log(form.data);
        submit('get', '/');
    };

    return (
        <section className="container mx-auto space-y-6">
            <div className="flex flex-row gap-4">
                <form onSubmit={handleSearch} noValidate className="w-full flex-1">
                    <FieldGroup>
                        <Field data-invalid={!!form.errors.keyword}>
                            <div className="relative flex items-center">
                                <span className="absolute left-2 text-sm text-muted-foreground">Cari data :</span>
                                <Input
                                    type="text"
                                    className="w-full ps-20 pe-10.5 align-middle text-sm placeholder:text-sm"
                                    placeholder="Masukkan kata kunci..."
                                    autoComplete="keyword"
                                    tabIndex={1}
                                    {...bind('keyword')}
                                />
                                <button
                                    type="submit"
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
