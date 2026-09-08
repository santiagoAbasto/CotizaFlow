import type { InputHTMLAttributes } from 'react';
type Props = InputHTMLAttributes<HTMLInputElement> & { label: string; error?: string; multiline?: boolean };
export default function Field({ label, error, multiline, id, ...props }: Props) {
    const shared = {
        id,
        'aria-invalid': Boolean(error),
        'aria-describedby': error ? id + '-error' : undefined,
        className: 'input',
    };
    return (
        <div className="field">
            <label htmlFor={id}>
                {label}
                {props.required && (
                    <span className="required" aria-hidden="true">
                        {' '}
                        *
                    </span>
                )}
            </label>
            {multiline ? (
                <textarea
                    {...shared}
                    name={props.name}
                    value={props.value}
                    rows={4}
                    maxLength={props.maxLength}
                    onChange={(e) => props.onChange?.(e as never)}
                />
            ) : (
                <input {...props} {...shared} />
            )}
            {error && (
                <p id={id + '-error'} className="field-error">
                    {error}
                </p>
            )}
        </div>
    );
}
