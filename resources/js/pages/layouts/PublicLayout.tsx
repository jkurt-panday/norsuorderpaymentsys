export default function PublicLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return (
        <main className="min-h-screen px-4 py-8 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            {children}
        </main>
    );
}