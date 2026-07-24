import { Link } from '@inertiajs/react';
import type { MouseEvent } from 'react';

interface FormInput {
    reference_number: string;
    full_name: string;
    email: string;
    contact_num: string;
    amount: number;
    request_type: string;
}

interface SuccessProps {
    formInput: FormInput;
    documents?: unknown[];
}

export default function Success({ formInput, documents }: SuccessProps) {
    return (
        <div className="form-container">
            <div className="success-container">
                <div className="icon">
                    <i className="fas fa-check-circle"></i>
                </div>
                <h2 className="text-success">Submission Successful!</h2>
                <p className="text-muted">
                    Your Order of Payment request has been submitted successfully.
                </p>

                <div className="my-4">
                    <h5 className="text-muted mb-2">Your Reference Number</h5>
                    <div className="reference-number-box">
                        {formInput.reference_number}
                    </div>
                    <p className="text-muted small mt-2">
                        <i className="fas fa-info-circle me-1"></i>
                        Please keep this reference number for tracking your request.
                    </p>
                </div>

                <div className="card mt-4">
                    <div className="card-body">
                        <h6 className="card-title text-muted">Submitted Details</h6>
                        <div className="row text-start">
                            <div className="col-md-6">
                                <p className="mb-1"><strong>Name:</strong> {formInput.full_name}</p>
                                <p className="mb-1"><strong>Email:</strong> {formInput.email}</p>
                                <p className="mb-1"><strong>Contact:</strong> {formInput.contact_num}</p>
                            </div>
                            <div className="col-md-6">
                                <p className="mb-1">
                                    <strong>Amount:</strong> ₱
                                    {Number(formInput.amount).toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    })}
                                </p>
                                <p className="mb-1"><strong>Request Type:</strong> {formInput.request_type}</p>
                                <p className="mb-1"><strong>Documents:</strong> {(documents ?? []).length} file(s)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="mt-4">
                    <Link href="/submit" className="btn btn-outline-primary">
                        <i className="fas fa-plus me-2"></i>Submit Another Request
                    </Link>
                    <button
                        type="button"
                        onClick={(e: MouseEvent<HTMLButtonElement>) => {
                            e.preventDefault();
                            window.print();
                        }}
                        className="btn btn-secondary"
                    >
                        <i className="fas fa-print me-2"></i>Print Receipt
                    </button>
                </div>
            </div>
        </div>
    );
}