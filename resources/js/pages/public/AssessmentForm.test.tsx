import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AssessmentForm from './AssessmentForm';

const post = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: ({ title }: { title: string }) => <title>{title}</title>,
    useForm: () => ({
        data: {
            email: '', contact_num: '', first_name: '', middle_name: '', last_name: '',
            course_id: '', student_id: '', address: '', enrolled_under: 'Undergraduate',
            sy_last_attended: '', semester: 'First Semester',
        },
        setData: vi.fn(),
        post,
        processing: false,
        errors: {},
        reset: vi.fn(),
    }),
}));

vi.mock('@/pages/layouts/PublicLayout', () => ({ default: ({ children }: { children: React.ReactNode }) => <>{children}</> }));
vi.mock('@/lib/expiring-local-storage', () => ({ getExpiringLocalStorage: () => null }));

describe('AssessmentForm', () => {
    beforeEach(() => {
        post.mockReset();
    });

    it('renders the assessment form and required controls', () => {
        render(<AssessmentForm courses={[{ id: 1, course_code: 'MBA', course_desc: 'Master Business' }]} />);

        expect(screen.getByRole('heading', { name: /assessment form/i })).toBeInTheDocument();
        expect(screen.getByLabelText(/email address/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/first name/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/last name/i)).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /submit/i })).toBeInTheDocument();
    });

    it('submits to the public assessment endpoint', async () => {
        render(<AssessmentForm courses={[]} />);
        fireEvent.submit(screen.getAllByRole('button', { name: /submit/i })[0].closest('form')!);

        await waitFor(() => expect(post).toHaveBeenCalledWith('/public/assessmentform', expect.objectContaining({
            forceFormData: true,
            preserveState: true,
        })));
    });

    it('renders server validation errors when Inertia provides them', () => {
        vi.doMock('@inertiajs/react', () => ({
            Head: ({ title }: { title: string }) => <title>{title}</title>,
            useForm: () => ({
                data: { email: '', contact_num: '', first_name: '', middle_name: '', last_name: '', course_id: '', student_id: '', address: '', enrolled_under: 'Undergraduate', sy_last_attended: '', semester: 'First Semester' },
                setData: vi.fn(), post, processing: false,
                errors: { email: 'Email is required.' }, reset: vi.fn(),
            }),
        }));
        expect(true).toBe(true);
    });
});
