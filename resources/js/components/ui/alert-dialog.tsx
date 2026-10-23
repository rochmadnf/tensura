import { AlertDialog as AlertDialogPrimitive } from '@base-ui/react/alert-dialog';
import { cn } from 'cn';
import type { ComponentProps } from 'react';

function AlertDialog(props: AlertDialogPrimitive.Root.Props) {
    return <AlertDialogPrimitive.Root {...props} />;
}

function AlertDialogTrigger(props: AlertDialogPrimitive.Trigger.Props) {
    return <AlertDialogPrimitive.Trigger data-slot="alert-dialog-trigger" {...props} />;
}

function AlertDialogContent({ className, children, ...props }: AlertDialogPrimitive.Popup.Props) {
    return (
        <AlertDialogPrimitive.Portal>
            <AlertDialogPrimitive.Backdrop className="fixed inset-0 z-50 bg-black/10 backdrop-blur-xs" />
            <AlertDialogPrimitive.Popup
                data-slot="alert-dialog-content"
                className={cn(
                    'fixed top-1/2 left-1/2 z-50 flex w-[calc(100%_-_2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 flex-col gap-4 rounded-xl border bg-popover p-6 text-popover-foreground shadow-lg',
                    className,
                )}
                {...props}
            >
                {children}
            </AlertDialogPrimitive.Popup>
        </AlertDialogPrimitive.Portal>
    );
}

function AlertDialogTitle({ className, ...props }: AlertDialogPrimitive.Title.Props) {
    return <AlertDialogPrimitive.Title className={cn('text-lg font-semibold', className)} {...props} />;
}

function AlertDialogDescription({ className, ...props }: AlertDialogPrimitive.Description.Props) {
    return <AlertDialogPrimitive.Description className={cn('text-sm text-muted-foreground', className)} {...props} />;
}

function AlertDialogFooter({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('flex flex-col-reverse gap-2 sm:flex-row sm:justify-end', className)} {...props} />;
}

function AlertDialogCancel(props: AlertDialogPrimitive.Close.Props) {
    return <AlertDialogPrimitive.Close {...props} />;
}

export {
    AlertDialog,
    AlertDialogTrigger,
    AlertDialogContent,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogCancel,
};
