USE ParadiseResort;

-- 1. Popolamento Tabelle Indipendenti Base
INSERT INTO users (first_name, last_name, email, password) VALUES
('Marco', 'Rossi', 'admin@paradise.com', '$2y$10$hnQP0q4um.IHuS9IX8BiM.nitA0x.aZTb882dD1kWOVy.ecxGS9yG'), -- Id 1 (Admin) Password: password
('Giulia', 'Bianchi', 'reception@paradise.com', '$2y$10$hnQP0q4um.IHuS9IX8BiM.nitA0x.aZTb882dD1kWOVy.ecxGS9yG'), -- Id 2 (Receptionist)
('Alessandro', 'Verdi', 'alessandro.v@email.com', '$2y$10$hnQP0q4um.IHuS9IX8BiM.nitA0x.aZTb882dD1kWOVy.ecxGS9yG'), -- Id 3 (Guest)
('Sofia', 'Neri', 'sofia.n@email.com', '$2y$10$hnQP0q4um.IHuS9IX8BiM.nitA0x.aZTb882dD1kWOVy.ecxGS9yG'), -- Id 4 (Guest)
('Lorenzo', 'Gialli', 'lorenzo.g@email.com', '$2y$10$hnQP0q4um.IHuS9IX8BiM.nitA0x.aZTb882dD1kWOVy.ecxGS9yG'); -- Id 5 (Guest)

INSERT INTO gruppi (name, description) VALUES
('Admin', 'Amministratore di sistema con accesso totale'),
('Receptionist', 'Staff della reception, gestisce stanze e prenotazioni'),
('Guest', 'Cliente registrato del resort');

INSERT INTO services (script_name, description) VALUES
('index.php', 'Dashboard principale'),
('rooms.php', 'Gestione Camere'),
('bookings.php', 'Gestione Prenotazioni'),
('users.php', 'Gestione Utenti Registrati'),
('staff.php', 'Gestione Staff'),
('services.php', 'Gestione Permessi'),
('categories.php', 'Gestione Categorie Camere'),
('activity.php', 'Gestione Attività'),
('profile.php', 'Area personale'),
('restaurant_bookings.php', 'Gestione Prenotazioni Ristorante'),
('segnalazioni.php', 'Gestione Segnalazioni');

-- 2. Popolamento Tabelle di Giunzione ACL
INSERT INTO user_gruppi (user_id, group_id) VALUES
(1, 1), -- Marco Rossi -> Admin
(2, 2), -- Giulia Bianchi -> Receptionist
(3, 3), -- Alessandro Verdi -> Guest
(4, 3), -- Sofia Neri -> Guest
(5, 3); -- Lorenzo Gialli -> Guest

INSERT INTO group_services (group_id, service_id) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8), (1, 9), (1, 10), (1, 11), -- L'Admin vede tutto
(2, 1), (2, 2), (2, 3), (2, 8), (2, 9), (2, 10), (2, 11),                              -- Receptionist vede dashboard, camere, prenotazioni, attività, profilo, prenotazioni ristorante, segnalazioni
(3, 9);                                                                 -- Il Guest vede solo il proprio profilo

-- 3. Popolamento Catalogo Stanze
INSERT INTO room_categories (name, description, base_price, capacity, image_url) VALUES
('Deluxe Singola', 'Elegante stanza per viaggiatori singoli con finiture di pregio', 150.00, 1, 'deluxe_singola.jpg'),
('Junior Suite', 'Ampia e raffinata con letto king size e area relax', 280.00, 2, 'junior_suite.jpg'),
('Suite Vista Mare', 'Lussuosa suite con balcone panoramico e vasca idromassaggio', 450.00, 4, 'suite_vista_mare.jpg'),
('Family Suite', 'Spaziosa suite per famiglie, due ambienti separati e comfort esclusivi', 550.00, 4, 'family_suite.jpg'),
('Presidential Suite', 'La massima espressione del lusso, vista mozzafiato, maggiordomo e piscina privata', 1200.00, 6, 'presidential_suite.jpg');

INSERT INTO rooms (room_number, category_id, floor, status) VALUES
('101', 1, 1, 'available'),
('102', 1, 1, 'cleaning'),
('201', 2, 2, 'available'),
('202', 2, 2, 'available'),
('301', 3, 3, 'available'),
('302', 3, 3, 'maintenance'),
('401', 4, 4, 'available'),
('402', 4, 4, 'available'),
('501', 5, 5, 'available');

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

-- 6. Popolamento Prenotazioni Ristorante
INSERT INTO restaurant_reservations (user_id, reservation_date, meal_type, reservation_time, guests, status) VALUES
(3, '2026-08-01', 'Cena', '20:30', 2, 'Confirmed'),
(4, '2026-08-02', 'Pranzo', '13:00', 4, 'Pending');