import { DatePickerInput } from '@/components/date-picker-input';
import { Badge } from '@/components/ui/badge';
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { formatDisplayCurrency, toCanonicalNumber } from '@/lib/canonical-numbers';
import type { ResolvedQuestionField } from '@/schemas/work-calendar';

export type QuestionFieldProps = {
    id: string;
    path: string;
    label: string;
    required: boolean;
    disabled?: boolean;
    error?: string;
    resolved?: ResolvedQuestionField | null;
    isResolving?: boolean;
};

export function RadioQuestionField({
    id,
    path,
    label,
    required,
    disabled = false,
    error,
    options = [],
    value,
    onChange,
}: QuestionFieldProps & {
    options?: { id?: string | null; code: string; label: string }[];
    value: string | null | undefined;
    onChange: (code: string) => void;
}) {
    return (
        <Field data-invalid={!!error}>
            <FieldLabel htmlFor={`field-${id}`}>
                {label}
                {required ? ' *' : ''}
            </FieldLabel>
            <RadioGroup
                id={`field-${id}`}
                name={path}
                value={value ?? ''}
                onValueChange={(val) => onChange(val as string)}
                disabled={disabled}
                className="gap-2.5 pt-1"
            >
                {options.map((opt) => (
                    <div key={opt.id ?? opt.code} className="flex items-center gap-2.5">
                        <RadioGroupItem value={opt.code} id={`radio-${id}-${opt.code}`} />
                        <FieldLabel htmlFor={`radio-${id}-${opt.code}`} className="cursor-pointer font-normal">
                            {opt.label}
                        </FieldLabel>
                    </div>
                ))}
            </RadioGroup>
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}

export function NumberQuestionField({
    id,
    path,
    label,
    required,
    disabled = false,
    error,
    resolved,
    isResolving = false,
    value,
    allowNegative = false,
    onChange,
}: QuestionFieldProps & {
    value: string | null | undefined;
    allowNegative?: boolean;
    onChange: (val: string) => void;
}) {
    return (
        <Field data-invalid={!!error}>
            <FieldLabel htmlFor={`field-${id}`}>
                {label}
                {required ? ' *' : ''}
            </FieldLabel>
            <Input
                id={`field-${id}`}
                name={path}
                value={value ?? ''}
                disabled={disabled}
                placeholder={allowNegative ? 'Cth: 12.5 atau -12' : 'Cth: 12.5'}
                onChange={(e) => {
                    const raw = e.target.value;
                    const canonical = toCanonicalNumber(raw);
                    onChange(canonical ?? raw);
                }}
            />
            {isResolving ? (
                <FieldDescription className="animate-pulse text-muted-foreground">
                    Menghitung terbilang...
                </FieldDescription>
            ) : resolved?.words ? (
                <div className="flex flex-wrap items-center gap-2 pt-1 text-sm">
                    <Badge variant="secondary">Terbilang</Badge>
                    <span className="font-medium">{resolved.words}</span>
                </div>
            ) : null}
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}

export function MoneyQuestionField({
    id,
    path,
    label,
    required,
    disabled = false,
    error,
    resolved,
    isResolving = false,
    value,
    isInteger = false,
    allowNegative = false,
    onChange,
}: QuestionFieldProps & {
    value: string | null | undefined;
    isInteger?: boolean;
    allowNegative?: boolean;
    onChange: (val: string) => void;
}) {
    return (
        <Field data-invalid={!!error}>
            <FieldLabel htmlFor={`field-${id}`}>
                {label}
                {required ? ' *' : ''}
            </FieldLabel>
            <Input
                id={`field-${id}`}
                name={path}
                value={value ?? ''}
                disabled={disabled}
                placeholder={isInteger ? 'Cth: 1.250.000' : 'Cth: 1.250.000,50'}
                onChange={(e) => {
                    const raw = e.target.value;
                    const canonical = toCanonicalNumber(raw);
                    onChange(canonical ?? raw);
                }}
            />
            {isResolving ? (
                <FieldDescription className="animate-pulse text-muted-foreground">
                    Menghitung nominal dan terbilang...
                </FieldDescription>
            ) : resolved ? (
                <div className="flex flex-col gap-1 pt-1 text-sm">
                    {resolved.display && (
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-muted-foreground">Tampilan:</span>
                            <span className="font-medium">{resolved.display}</span>
                        </div>
                    )}
                    {resolved.words && (
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">Terbilang</Badge>
                            <span className="font-medium">{resolved.words}</span>
                        </div>
                    )}
                </div>
            ) : value ? (
                <div className="pt-1 text-xs text-muted-foreground">
                    Format: {formatDisplayCurrency(value, isInteger)}
                </div>
            ) : null}
            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}

export function DateRangeQuestionField({
    id,
    path,
    label,
    required,
    disabled = false,
    error,
    resolved,
    isResolving = false,
    value,
    onChange,
}: QuestionFieldProps & {
    value: { start_date?: string; end_date?: string } | null | undefined;
    onChange: (val: { start_date: string; end_date: string }) => void;
}) {
    const startDate = value?.start_date ?? '';
    const endDate = value?.end_date ?? '';

    const handleStartChange = (start: string) => {
        onChange({ start_date: start, end_date: endDate });
    };

    const handleEndChange = (end: string) => {
        onChange({ start_date: startDate, end_date: end });
    };

    const calendarState = resolved?.calendar_state;

    return (
        <Field data-invalid={!!error}>
            <FieldLabel htmlFor={`field-${id}`}>
                {label}
                {required ? ' *' : ''}
            </FieldLabel>
            <div className="grid gap-3 sm:grid-cols-2">
                <Field>
                    <FieldLabel htmlFor={`start-${id}`} className="text-xs text-muted-foreground">
                        Tanggal Mulai
                    </FieldLabel>
                    <DatePickerInput
                        id={`start-${id}`}
                        name={`${path}.start_date`}
                        value={startDate}
                        disabled={disabled}
                        onValueChange={handleStartChange}
                    />
                </Field>
                <Field>
                    <FieldLabel htmlFor={`end-${id}`} className="text-xs text-muted-foreground">
                        Tanggal Akhir
                    </FieldLabel>
                    <DatePickerInput
                        id={`end-${id}`}
                        name={`${path}.end_date`}
                        value={endDate}
                        disabled={disabled}
                        onValueChange={handleEndChange}
                    />
                </Field>
            </div>

            {isResolving ? (
                <FieldDescription className="animate-pulse text-muted-foreground">
                    Menghitung durasi hari kerja...
                </FieldDescription>
            ) : resolved?.display ? (
                <div className="flex flex-wrap items-center gap-2 pt-1 text-sm">
                    <Badge variant="secondary">Durasi</Badge>
                    <span className="font-semibold text-foreground">{resolved.display}</span>
                    {calendarState?.state === 'missing' && <Badge variant="destructive">Kalender belum siap</Badge>}
                    {calendarState?.state === 'stale' && <Badge variant="warning">Kalender diperbarui</Badge>}
                </div>
            ) : null}

            {error && <FieldError>{error}</FieldError>}
        </Field>
    );
}
