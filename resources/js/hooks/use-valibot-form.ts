import { useForm } from '@inertiajs/react';
import type { ChangeEvent } from 'react';
import * as v from 'valibot';

type Method = 'post' | 'put' | 'patch' | 'delete';

type SubmitOptions = Parameters<ReturnType<typeof useForm>['post']>[1];

type FormSchema = v.GenericSchema<Record<string, any>>;

type LooseForm = {
    data: Record<string, unknown>;
    errors: Record<string, string | undefined>;
    setData: (key: string, value: unknown) => void;
    setError: (errors: Record<string, string>) => void;
    clearErrors: (...fields: string[]) => void;
    transform: (callback: (data: any) => unknown) => void;
} & Record<Method, (url: string, options?: SubmitOptions) => void>;

function toErrors(issues: [v.BaseIssue<unknown>, ...v.BaseIssue<unknown>[]]) {
    const errors: Record<string, string> = {};

    for (const issue of issues) {
        const path = v.getDotPath(issue);

        if (path && !errors[path]) {
            errors[path] = issue.message;
        }
    }

    return errors;
}

function toInputValue(value: unknown): string {
    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return '';
}

export function useValibotForm<TSchema extends FormSchema>(schema: TSchema, initialValues: v.InferInput<TSchema>) {
    type Data = v.InferInput<TSchema>;

    const form = useForm<Data>(initialValues);
    const loose = form as unknown as LooseForm;

    const submit = (method: Method, url: string, options?: SubmitOptions) => {
        const result = v.safeParse(schema, loose.data);

        if (!result.success) {
            loose.setError(toErrors(result.issues));
            return false;
        }

        loose.clearErrors();
        loose.transform(() => result.output);
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
