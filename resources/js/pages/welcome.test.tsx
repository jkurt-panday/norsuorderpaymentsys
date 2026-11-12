import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Welcome from './welcome';

let user: { id: number; name: string } | null = null;

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title: string }) => <title>{title}</title>,
    Link: ({
        href,
        children,
        ...props
    }: React.ComponentProps<'a'> & { href: string | { url: string } }) => (
        <a href={typeof href === 'string' ? href : href.url} {...props}>
            {children}
        </a>
    ),
    usePage: () => ({ props: { auth: { user } } }),
}));

vi.mock('@/routes', () => ({
    dashboard: () => ({ url: '/dashboard', method: 'get' }),
    home: () => ({ url: '/', method: 'get' }),
    login: () => ({ url: '/login', method: 'get' }),
}));

vi.mock('@/routes/public/opform', () => ({
    create: () => ({ url: '/public/opform', method: 'get' }),
}));

vi.mock('@/routes/public/assessmentform', () => ({
    create: () => ({ url: '/public/assessmentform', method: 'get' }),
}));

describe('Welcome', () => {
    beforeEach(() => {
        user = null;
    });

    it('guides guests to both public accounting services', () => {
        render(<Welcome />);

        expect(
            screen.getByRole('heading', {
                name: /^order of payment system$/i,
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', {
                name: /accounting services in one place/i,
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('heading', {
                name: /built for simpler accounting requests/i,
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/do not require a payor account/i),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /request an order of payment/i }),
        ).toHaveAttribute('href', '/public/opform');
        expect(
            screen.getByRole('link', { name: /request an assessment/i }),
        ).toHaveAttribute('href', '/public/assessmentform');
        expect(
            screen.getByRole('link', { name: /staff sign in/i }),
        ).toHaveAttribute('href', '/login');
    });

    it('sends authenticated staff to the dashboard', () => {
        user = { id: 1, name: 'Accounting Staff' };

        render(<Welcome />);

        expect(
            screen.getByRole('link', { name: /open dashboard/i }),
        ).toHaveAttribute('href', '/dashboard');
    });
});
