import {
    safeParse,
    setSpecificMessage,
    string,
    nonEmpty,
    minLength,
    maxLength,
    email,
    length,
    file,
    mimeType,
    maxSize,
    type GenericSchema,
    type InferInput,
    type InferOutput,
} from 'valibot';

setSpecificMessage(string, ':attribute harus berupa teks.');
setSpecificMessage(nonEmpty, ':attribute wajib diisi.');
setSpecificMessage(email, ':attribute harus berupa email yang valid.');
setSpecificMessage(file, ':attribute harus berupa berkas.');
setSpecificMessage(mimeType, ':attribute harus berformat JPG, JPEG, PNG, atau WebP.');
setSpecificMessage(maxSize, (issue) => `:attribute maksimal ${issue.requirement / 1024 / 1024} MB.`);
setSpecificMessage(minLength, (issue) => `:attribute minimal ${issue.requirement} karakter.`);
setSpecificMessage(length, (issue) => `:attribute harus ${issue.requirement} karakter.`);
setSpecificMessage(maxLength, (issue) => `:attribute maksimal ${issue.requirement} karakter.`);

type Errors = Record<string, string[]>;

export function defineRequest<TSchema extends GenericSchema>(
    schema: TSchema,
    attributes: Partial<Record<keyof InferInput<TSchema> & string, string>> = {},
) {
    return {
        schema,
        attributes,
        validate(
            input: unknown,
        ):
            | { success: true; data: InferOutput<TSchema>; errors: {} }
            | { success: false; data: undefined; errors: Errors } {
            const result = safeParse(schema, input);

            if (result.success) {
                return { success: true, data: result.output, errors: {} };
            }

            const errors: Errors = {};
            for (const issue of result.issues) {
                const key = issue.path?.map((p) => p.key).join('.') ?? '_root';
                const label = (attributes as Record<string, string>)[key] ?? key;
                (errors[key] ??= []).push(issue.message.replaceAll(':attribute', label));
            }

            return { success: false, data: undefined, errors };
        },
    };
}
