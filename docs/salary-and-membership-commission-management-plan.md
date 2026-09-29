# Salary and Membership Commission Management

## Goal

Give gym owners one auditable workflow for fixed trainer/staff salaries and commission earned from the explicitly identified PT or commissionable extra on a membership. Commission is earned only as member money is collected, recurring rules follow renewals, and payouts post automatically to the gym finance ledger.

## Commercial model

A membership cycle stores the existing base plan price and `pt_custom_fee`, presented to owners as **PT / commissionable extra**. Joining fees, partial-month fees, and unrelated adjustments are excluded from the commission pool.

Each membership can contain multiple allocations:

- Recipient: trainer or staff member attached to the gym.
- Category: PT commission or sales commission.
- Calculation: percentage of the extra or a fixed total amount.
- Frequency: one time or every renewal.

The expected total of all allocations cannot exceed the commissionable extra. The gym retains the remainder.

## Collection rule

Recorded payments cover the non-commissionable membership amount first. The part collected above that threshold enters the commission pool. Percentage allocations earn their percentage of each collected-extra increment. Fixed allocations accrue proportionally, so a partial payment cannot make the gym pay the entire fixed commission early.

Reversed payments rebuild the membership earnings and mark earnings without a valid recorded payment as reversed.

## Renewal rule

Renewal copies the prior cycle's PT / commissionable extra. Only allocations marked recurring are copied. Percentage rules recalculate against the new cycle extra; fixed rules retain their configured amount. Each renewal receives a new snapshot so later edits do not silently change prior cycles.

## Monthly compensation

Each trainer or staff member can have one effective-dated compensation profile per gym with:

- worker type;
- monthly fixed salary;
- branch scope;
- payout day;
- effective start and end dates; and
- active status.

Generating a month creates or refreshes draft statements from fixed salaries and commission earnings dated in that month. Paid or partially paid statements are not silently recalculated.

The statement calculation is:

`fixed salary + collected commission + adjustments - deductions = net payable`

Recording a payout updates the statement and creates exactly one linked `payroll` outflow in the existing gym ledger.

## Owner workflow

1. Configure fixed salary under **Finance → Salary & Commission**.
2. During membership assignment, enter the PT / commissionable extra and add trainer/staff split rows.
3. Collect member payments normally.
4. Review calculated earnings in the commission audit.
5. Generate the monthly statements.
6. Record full or partial payouts.
7. Reconcile the linked payroll entries in the Finance Ledger.

## Validation requirements

- Recipient belongs to the same gym.
- Percentage is between 0 and 100.
- Combined expected commission does not exceed the extra.
- No commission is earned against unpaid dues or the base membership price.
- One-time allocations are excluded from renewal.
- Payment retries cannot create duplicate earnings.
- A payout cannot exceed the remaining statement balance.
- Every payout produces one source-linked ledger entry.
- Gym and branch authorization is enforced on configuration and payout endpoints.

## Deployment

Run the Laravel migration, clear application caches, and restart long-running PHP/queue processes after deployment. Existing memberships remain valid; their commission split is empty until configured. Existing manual payroll ledger entries are preserved and are not converted into statements.
