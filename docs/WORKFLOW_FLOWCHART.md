# Core System Workflow & Edge Cases Flowchart

This document outlines the end-to-end lifecycle of an Order of Payment (OP), from student submission through staff assessment, cashier payment, and receipt fulfillment—including edge cases, dead ends, and state-machine vulnerabilities.

---

## 1. End-to-End Workflow Diagram

```mermaid
flowchart TD
    %% Actors
    Student(["👤 Student / Client"])
    Staff(["👔 Staff"])
    Cashier(["💳 Cashier"])

    %% Step 1: Student Submission
    subgraph Step1 ["1. Order of Payment Request"]
        A["Visits /opform (Web)"] --> B{"Rate Limit Check\n(throttle:20,1)"}
        B -- Exceeded --> B_Fail["❌ 429 Too Many Requests"]
        B -- Pass --> C["Fills Student Info & Uploads Documents"]
        C --> D{"File Validation\n(MIME, size <= 10MB, count <= 5)"}
        D -- Invalid --> D_Fail["❌ Validation Error"]
        D -- Valid --> E["Creates FormInput (Status: Unprocessed)\nSets session('success_reference_number')"]
        E --> F["Displays Success Page with Reference Number"]
    end

    %% Step 2: Staff Review & Assessment
    subgraph Step2 ["2. Staff Review & Assessment"]
        G["Staff Dashboard: Views Pending FormInputs"] --> H{"Review Details & Docs"}
        H -- Reject --> I["Mark Form as Incomplete / Rejected"]
        H -- Approve --> J["Assign UACS / Breakdown Fees & Matching Student"]
        J --> K["Save StaffInput (Status: Processed / OP Issued)"]
        K --> L["Notify Student via Email (with OP PDF)"]
    end

    %% Step 3: Cashier Payment
    subgraph Step3 ["3. Payment Processing"]
        M["Search Order of Payment\n(by Ref Number or Student No.)"] --> N{"Verify Bill & Status"}
        N -- Processed --> O["Record Payment Transaction (OR Number, Date, Amount)"]
        O --> P["Update Status: Paid"]
        P --> Q["Auto-post Transaction to Accounting Ledger\n(GraduateLedger / LawSchoolLedger)"]
    end

    %% Step 4: Completion & Fulfillment
    subgraph Step4 ["4. Fulfillment & Proof of Payment"]
        R{"Access Receipt / Print\n(/public/success/{ref}/print)"}
        R -- Session Active OR User Email Matches --> S["✅ View / Print Official Receipt"]
        R -- No Session & Guest --> R_Fail["❌ 404 Not Found"]
        
        T{"Direct Download\n(/public/success/{ref}/download)"}
        T -- Session Active --> U["✅ Download PDF Receipt"]
        T -- Session Expired / Guest --> T_Trap["⚠️ DEAD END: 404 (Missing Auth Fallback)"]
    end

    %% Transitions between actors
    Student --> A
    F -. Reference Number .-> Staff
    Staff --> G
    L -. Order of Payment Issued .-> Cashier
    Cashier --> M
    Q -. Payment Complete .-> Student
    Student --> R
    Student --> T

    %% Styling
    classDef client fill:#e0f2fe,stroke:#0284c7,stroke-width:2px,color:#0f172a;
    classDef staff fill:#fef3c7,stroke:#d97706,stroke-width:2px,color:#0f172a;
    classDef cashier fill:#dcfce7,stroke:#16a34a,stroke-width:2px,color:#0f172a;
    classDef reject fill:#fee2e2,stroke:#dc2626,stroke-width:2px,color:#0f172a;
    classDef warning fill:#fef08a,stroke:#ca8a04,stroke-width:2px,color:#0f172a;

    class Student,A,C,E,F,S,U client;
    class Staff,G,H,J,K,L staff;
    class Cashier,M,N,O,P,Q cashier;
    class B_Fail,D_Fail,I,R_Fail reject;
    class T_Trap warning;
```

---

## 2. Identified Edge Cases & Vulnerabilities

```mermaid
flowchart LR
    subgraph EdgeCases ["⚠️ Critical State-Machine & Edge Case Vulnerabilities"]
        direction TB

        E1["1. Receipt Download Dead End"]
        E1_Desc["printReceipt allows auth user matching email.<br/>downloadReceipt ONLY checks session.<br/>Closed browser = permanent 404."]
        E1 --- E1_Desc

        E2["2. Modifying Paid OPs"]
        E2_Desc["Staff can edit amount via updateDetails().<br/>Ledger row is NOT updated.<br/>Creates silent ledger divergence."]
        E2 --- E2_Desc

        E3["3. Status Demotion Gap"]
        E3_Desc["Reversals only trigger on status = 'cancelled'.<br/>Changing 'paid' -> 'processed' leaves<br/>payment active in accounting ledger."]
        E3 --- E3_Desc

        E4["4. Cashier Paying Unapproved OPs"]
        E4_Desc["Cashier API endpoint does not require<br/>current status to be 'processed'.<br/>Direct PUT can mark draft OP as 'paid'."]
        E4 --- E4_Desc
    end

    classDef issue fill:#fff7ed,stroke:#ea580c,stroke-width:2px,color:#0f172a;
    class E1,E2,E3,E4 issue;
```

---

## 3. Summary of Workflow Steps

| Phase | Actor | Endpoint | Main Action | Key Validations & Guards |
| :--- | :--- | :--- | :--- | :--- |
| **1. Submission** | Student / Client | `POST /opform` | Submits payer details & documents | `throttle:20,1`, MIME verification, max 10MB, max 5 files |
| **2. Review** | Staff | `POST /staff/requests/process` | Validates details & assigns fees | Prevents duplicate processing, links student term |
| **3. Payment** | Cashier | `PUT /cashier/requests/{id}/payment` | Inputs OR number and marks paid | Validates `or_no` & `or_date`, posts to student ledger |
| **4. Fulfillment** | Student / Client | `GET /public/success/{ref}/print` | Prints / views official proof | Session check or authenticated matching email check |
