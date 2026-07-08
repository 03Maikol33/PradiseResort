USE ParadiseResort;

-- 1. Popolamento Tabelle Indipendenti Base
INSERT INTO users (first_name, last_name, email, password) VALUES
('Marco', 'Rossi', 'admin@paradise.com', '$10$EcUFtRTyktApSLIwseID4.fiaei8P0LtIANGHxMlw74Rfnl.62t46'), -- Id 1 (Admin) Password: password
('Giulia', 'Bianchi', 'reception@paradise.com', '$10$EcUFtRTyktApSLIwseID4.fiaei8P0LtIANGHxMlw74Rfnl.62t46'), -- Id 2 (Receptionist)
('Alessandro', 'Verdi', 'alessandro.v@email.com', '$10$EcUFtRTyktApSLIwseID4.fiaei8P0LtIANGHxMlw74Rfnl.62t46'), -- Id 3 (Guest)
('Sofia', 'Neri', 'sofia.n@email.com', '$10$EcUFtRTyktApSLIwseID4.fiaei8P0LtIANGHxMlw74Rfnl.62t46'), -- Id 4 (Guest)
('Lorenzo', 'Gialli', 'lorenzo.g@email.com', '$10$EcUFtRTyktApSLIwseID4.fiaei8P0LtIANGHxMlw74Rfnl.62t46'); -- Id 5 (Guest)

INSERT INTO gruppi (name, description) VALUES
('Admin', 'Amministratore di sistema con accesso totale'),
('Receptionist', 'Staff della reception, gestisce stanze e prenotazioni'),
('Guest', 'Cliente registrato del resort');

INSERT INTO services (script_name, description) VALUES
('admin_dashboard.php', 'Dashboard principale di amministrazione'),
('manage_rooms.php', 'Pannello CRUD per le stanze e le categorie'),
('manage_bookings.php', 'Pannello per visualizzare e modificare lo stato delle prenotazioni'),
('manage_users.php', 'Pannello per la gestione degli account e dei permessi'),
('profile.php', 'Area personale dell\'utente loggato');

-- 2. Popolamento Tabelle di Giunzione ACL
INSERT INTO user_gruppi (user_id, group_id) VALUES
(1, 1), -- Marco Rossi -> Admin
(2, 2), -- Giulia Bianchi -> Receptionist
(3, 3), -- Alessandro Verdi -> Guest
(4, 3), -- Sofia Neri -> Guest
(5, 3); -- Lorenzo Gialli -> Guest

INSERT INTO group_services (group_id, service_id) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), -- L'Admin vede tutto
(2, 1), (2, 2), (2, 3), (2, 5),         -- Il Receptionist non vede la gestione utenti
(3, 5);                                 -- Il Guest vede solo il proprio profilo

-- 3. Popolamento Catalogo Stanze
INSERT INTO room_categories (name, description, base_price, capacity, image_url) VALUES
('Singola Standard', 'Stanza comoda per viaggiatori singoli', 75.00, 1, 'singola.jpg'),
('Doppia Superior', 'Stanza spaziosa con letto matrimoniale', 120.00, 2, 'doppia.jpg'),
('Suite Vista Mare', 'Lussuosa suite con balcone panoramico', 250.00, 4, 'suite.jpg');

INSERT INTO rooms (room_number, category_id, floor, status) VALUES
('101', 1, 1, 'available'),
('102', 1, 1, 'cleaning'),
('201', 2, 2, 'available'),
('202', 2, 2, 'available'),
('301', 3, 3, 'available'),
('302', 3, 3, 'maintenance');

-- 4. Popolamento Stati e Servizi Extra (Amenities)
INSERT INTO booking_statuses (name) VALUES
('In Cart'),
('Pending'),
('Confirmed'),
('Cancelled'),
('Completed');

INSERT INTO ticket_statuses (name) VALUES
('Open'),
('In Progress'),
('Resolved');

INSERT INTO amenities (name, description, price) VALUES
('Accesso SPA', 'Ingresso giornaliero al centro benessere', 35.00),
('Colazione in Camera', 'Servizio in camera per la colazione', 15.00),
('Navetta Aeroporto', 'Trasferimento da e per l\'aeroporto', 50.00);

-- 5. Popolamento Prenotazioni e Dati Dipendenti
INSERT INTO bookings (user_id, room_id, status_id, check_in_date, check_out_date, total_price) VALUES
(3, 3, 5, '2026-06-10', '2026-06-15', 600.00), -- Prenotazione passata (Completata)
(4, 5, 3, '2026-08-01', '2026-08-07', 1570.00), -- Prenotazione futura (Confermata)
(5, 1, 1, '2026-07-20', '2026-07-22', 150.00); -- Prenotazione non completata (Nel Carrello)

INSERT INTO booking_amenities (booking_id, amenity_id, quantity) VALUES
(2, 1, 2), -- Sofia ha prenotato 2 accessi SPA
(2, 3, 1); -- Sofia ha prenotato 1 navetta

INSERT INTO invoices (booking_id, total_amount, payment_status) VALUES
(1, 600.00, 'paid'),
(2, 1570.00, 'paid');

INSERT INTO reviews (user_id, room_category_id, rating, comment) VALUES
(3, 2, 5, 'Soggiorno fantastico, la doppia superior era pulitissima e molto accogliente.');

INSERT INTO maintenance_tickets (room_id, reported_by_user_id, status_id, issue_description) VALUES
(6, 2, 2, 'Il condizionatore perde acqua, chiamato il tecnico.'),
(2, 2, 3, 'Sostituzione lampadina fulminata in bagno completata.');