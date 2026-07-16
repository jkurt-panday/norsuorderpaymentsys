import AuthCardLayout from '@/layouts/auth/auth-card-layout'; // Point to your new component

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
        <AuthCardLayout title={title} description={description}>
            <div className="space-y-4">
                {children}
            </div>
        </AuthCardLayout>
    );
}