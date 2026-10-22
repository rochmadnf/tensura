import { useForm } from '@inertiajs/react';
import type { ChangeEvent } from 'react';
import type * as v from 'valibot';

type Method = 'get' | 'post' | 'put' | 'patch' | 'delete';

type SubmitOptions = Parameters<ReturnType<typeof useForm>['post']>[1];

type FormSchema = v.GenericSchema<Record<string, any>>;

type RequestLike<TSchema extends FormSchema> = {
    schema: TSchema;
    validate: (input: unknown) => {
        success: boolean;
        data?: unknown;
        errors: Record<string, string[]>;
    };
};

type LooseForm = {
    data: Record<string, unknown>;
    errors: Record<string, string | undefined>;
    setData: (key: string, value: unknown) => void;
    setError: (errors: Record<string, string>) => void;
    clearErrors: (...fields: string[]) => void;
    transform: (callback: (data: any) => unknown) => void;
} & Record<Method, (url: string, options?: SubmitOptions) => void>;

function toInputValue(value: unknown): string {
    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return '';
}

export function useValibotForm<TSchema extends FormSchema>(
    request: RequestLike<TSchema>,
    initialValues: v.InferInput<TSchema>,
) {
    type Data = v.InferInput<TSchema>;

    const form = useForm<Data>(initialValues);
    const loose = form as unknown as LooseForm;

    const submit = (method: Method, url: string, options?: SubmitOptions) => {
        const result = request.validate(loose.data);

        if (!result.success) {
            loose.setError(
                Object.fromEntries(Object.entries(result.errors).map(([key, messages]) => [key, messages[0]])),
            );
            return false;
        }

        loose.clearErrors();
        loose.transform(() => result.data);
        loose[method](url, options);

        return true;
    };

    const bind = <K extends keyof Data & string>(name: K) => ({
        id: name,
        name,
        value: toInputValue(loose.data[name]),
        'aria-invalid': !!loose.errors[name],
        onChange: (e: ChangeEvent<HTMLInputElement>) => {
            loose.setData(name, e.target.value);

            if (loose.errors[name]) {
                loose.clearErrors(name);
            }
        },
    });

    return { form, submit, bind };
}
