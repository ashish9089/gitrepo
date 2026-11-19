# MACHINEX — Machine & Production Program Manager

A responsive PHP + MySQL application based on the supplied notebook workflow.

## Stack
- PHP 8.0+ (PDO)
- MySQL / MariaDB
- Bootstrap 5.3, Bootstrap Icons, custom CSS, vanilla JavaScript
- XAMPP-compatible

## Install on XAMPP
1. Copy the `machine_program_app` folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`.
4. Import `database.sql` (this creates the `machine_program_manager` database and tables).
5. Check `config.php`. The default XAMPP values are host `127.0.0.1`, user `root`, blank password.
6. Open `http://localhost/machine_program_app/`.
7. Create the first account. The first account is automatically the **Manager**. Later public registrations are **Operators**.
8. The manager can promote an operator to manager from **Users**.

## Workflow
1. Sign in.
2. Add machines (e.g. M1, M2, M3).
3. Open a machine and add its programs (e.g. P1, P2).
4. Enter a program number, choose a figure, and enter F1/F2/F3 values.
5. Open a program to review its details, mark it Done, or print it.

## Roles and safeguards
- Manager: create machines/programs, mark programs done, delete machines/programs, and manage user roles.
- Operator: create machines/programs and mark programs done; cannot delete records or manage users.
- Passwords are stored with PHP `password_hash()`; SQL uses prepared statements.
- Public sign-up cannot self-assign manager privileges. The first account is the initial manager.

## Assumptions from the sketch
- The handwritten role names appear to be Manager and Operator.
- The figure dropdown has been seeded with `Figure 1` through `Figure 5` and `Other`. Replace these options in `index.php` with the actual figure names used at your workplace.
- F1, F2 and F3 are stored as text so they can contain numbers, dimensions, or short notes. If they must be numeric, add validation and change their database types.
- Printing uses the browser's print dialog. Select the printer connected to the computer or device. A website cannot silently print without browser/device permission.

## Production deployment checklist
Before exposing this app to the public internet, enable HTTPS, set a strong MySQL password, move database credentials outside the web root, add CSRF tokens and rate limiting, disable public registration or add manager invitations, and configure automated database backups.
