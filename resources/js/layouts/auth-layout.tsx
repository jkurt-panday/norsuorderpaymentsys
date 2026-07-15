import AuthSplitLayout from '@/layouts/auth/auth-split-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <AuthSplitLayout>
            <div className="space-y-2">
                {title && <h1 className="text-2xl font-semibold">{title}</h1>}
                {description && <p className="text-sm text-muted-foreground">{description}</p>}
                {children}
            </div>
        </AuthSplitLayout>
    );
}
