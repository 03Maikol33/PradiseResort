USE ParadiseResort;

-- 1. Popolamento Tabelle Indipendenti Base
INSERT INTO users (first_name, last_name, email, password, phone, image_url) VALUES
('Marco', 'Rossi', 'admin@paradise.com', '$2y$10$hnQP0q4um.IHuS9IX8BiM.nitA0x.aZTb882dD1kWOVy.ecxGS9yG', '+39 340 1234567', NULL), -- Id 1 (Admin) Password: password
('Giulia', 'Bianchi', 'reception@paradise.com', '$2y$10$hnQP0q4um.IHuS9IX8BiM.nitA0x.aZTb882dD1kWOVy.ecxGS9yG', '+39 349 9876543', NULL), -- Id 2 (Receptionist)
('Alessandro', 'Verdi', 'alessandro.v@email.com', '$2y$10$hnQP0q4um.IHuS9IX8BiM.nitA0x.aZTb882dD1kWOVy.ecxGS9yG', '+39 333 1122334', NULL), -- Id 3 (Guest)
('Sofia', 'Neri', 'sofia.n@email.com', '$2y$10$hnQP0q4um.IHuS9IX8BiM.nitA0x.aZTb882dD1kWOVy.ecxGS9yG', '+39 338 5566778', NULL), -- Id 4 (Guest)
('Lorenzo', 'Gialli', 'lorenzo.g@email.com', '$2y$10$hnQP0q4um.IHuS9IX8BiM.nitA0x.aZTb882dD1kWOVy.ecxGS9yG', '+39 320 4455667', NULL); -- Id 5 (Guest)

INSERT INTO gruppi (id, name, description) VALUES
(1, 'Admin', 'Amministratore di sistema con accesso totale'),
(2, 'Receptionist', 'Staff della reception, gestisce stanze e prenotazioni'),
(3, 'Guest', 'Cliente registrato del resort');

INSERT INTO services (id, script_name, description) VALUES
(1, 'index.php', 'Dashboard principale'),
(2, 'rooms.php', 'Gestione Camere'),
(3, 'bookings.php', 'Gestione Prenotazioni'),
(4, 'users.php', 'Gestione Utenti Registrati'),
(5, 'staff.php', 'Gestione Staff'),
(6, 'services.php', 'Gestione Permessi'),
(7, 'categories.php', 'Gestione Categorie Camere'),
(8, 'activity.php', 'Gestione Attività'),
(9, 'profile.php', 'Area personale'),
(10, 'restaurant_bookings.php', 'Gestione Prenotazioni Ristorante (Staff)'),
(11, 'segnalazioni.php', 'Gestione Segnalazioni (Staff)'),
(12, 'reviews.php', 'Gestione Recensioni (Esclusivo Admin)'),
(13, 'amenities.php', 'Gestione Servizi Aggiuntivi'),
(14, 'requested_services.php', 'Servizi Richiesti Dai Clienti'),
(15, 'review.php', 'Scrivi Recensione (Cliente)'),
(16, 'send-review.php', 'Invio Recensione (Cliente)'),
(17, 'report-ticket.php', 'Segnalazione Guasti / Manutenzione (Cliente)'),
(18, 'send-report-ticket.php', 'Invio Segnalazione Guasti (Cliente)'),
(19, 'restaurant_book.php', 'Prenotazione Ristorante (Cliente)');

-- 2. Popolamento Tabelle di Giunzione ACL
INSERT INTO user_gruppi (user_id, group_id) VALUES
(1, 1), -- Marco Rossi -> Admin
(2, 2), -- Giulia Bianchi -> Receptionist
(3, 3), -- Alessandro Verdi -> Guest
(4, 3), -- Sofia Neri -> Guest
(5, 3); -- Lorenzo Gialli -> Guest

INSERT INTO group_services (group_id, service_id) VALUES
-- Admin (id 1) vede ed accede a tutto
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8), (1, 9), (1, 10), (1, 11), (1, 12), (1, 13), (1, 14), (1, 15), (1, 16), (1, 17), (1, 18), (1, 19),

-- Receptionist (id 2) vede dashboard, camere, prenotazioni, attività, profilo, prenotazioni ristorante, segnalazioni, servizi richiesti e servizi cliente (MA NON reviews.php che è esclusivo admin)
(2, 1), (2, 2), (2, 3), (2, 8), (2, 9), (2, 10), (2, 11), (2, 14), (2, 15), (2, 16), (2, 17), (2, 18), (2, 19),

-- Guest (id 3) vede il proprio profilo e le pagine di servizio cliente (recensioni, segnalazioni, prenotazione ristorante)
(3, 9), (3, 15), (3, 16), (3, 17), (3, 18), (3, 19);

-- 3. Popolamento Catalogo Stanze
INSERT INTO room_categories (name, description, base_price, capacity, image_url) VALUES
('Deluxe Singola', 'Elegante stanza per viaggiatori singoli con finiture di pregio', 150.00, 1, 'deluxe_singola.jpg'),
('Junior Suite', 'Ampia e raffinata con letto king size e area relax', 280.00, 2, 'junior_suite.jpg'),
('Suite Vista Mare', 'Lussuosa suite con balcone panoramico e vasca idromassaggio', 450.00, 4, 'suite_vista_mare.jpg'),
('Family Suite', 'Spaziosa suite per famiglie, due ambienti separati e comfort esclusivi', 550.00, 4, 'family_suite.jpg'),
('Presidential Suite', 'La massima espressione del lusso, vista mozzafiato, maggiordomo e piscina privata', 1200.00, 6, 'presidential_suite.jpg');

INSERT INTO rooms (id, room_number, category_id, floor, status) VALUES
(1, '101', 1, 1, 'available'),
(2, '102', 1, 1, 'cleaning'),
(3, '201', 2, 2, 'available'),
(4, '202', 2, 2, 'available'),
(5, '301', 3, 3, 'available'),
(6, '302', 3, 3, 'maintenance'),
(7, '401', 4, 4, 'available'),
(8, '402', 4, 4, 'available'),
(9, '501', 5, 5, 'available');

-- 4. Popolamento Stati e Servizi Extra (Amenities)
INSERT INTO booking_statuses (id, name) VALUES
(1, 'In Cart'),
(2, 'Pending'),
(3, 'Confirmed'),
(4, 'Cancelled'),
(5, 'Completed');

INSERT INTO ticket_statuses (id, name) VALUES
(1, 'Open'),
(2, 'In Progress'),
(3, 'Resolved');

INSERT INTO amenities (id, name, description, price, image_url, is_suspended) VALUES
(1, 'Accesso SPA', 'Ingresso giornaliero al centro benessere esclusivo con saune e percorso termale', 35.00, 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=800&q=80', 0),
(2, 'Colazione in Camera', 'Servizio premium in camera per la colazione continentale o all\'americana', 15.00, 'https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?auto=format&fit=crop&w=800&q=80', 0),
(3, 'Navetta Aeroporto', 'Trasferimento privato di lusso da e per l\'aeroporto', 50.00, 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?auto=format&fit=crop&w=800&q=80', 0);

-- 5. Popolamento Prenotazioni e Dati Dipendenti
INSERT INTO bookings (id, user_id, room_id, status_id, check_in_date, check_out_date, total_price, staff_notes) VALUES
(1, 3, 3, 5, '2026-06-10', '2026-06-15', 600.00, 'Ospite regolare, richiesto cuscino aggiuntivo'), -- Prenotazione passata (Completata)
(2, 4, 5, 3, '2026-08-01', '2026-08-07', 1570.00, 'Arrivo previsto nel tardo pomeriggio'), -- Prenotazione futura (Confermata)
(3, 5, 1, 1, '2026-07-20', '2026-07-22', 150.00, NULL); -- Prenotazione non completata (Nel Carrello)

INSERT INTO booking_amenities (booking_id, amenity_id, quantity) VALUES
(2, 1, 2), -- Sofia ha prenotato 2 accessi SPA
(2, 3, 1); -- Sofia ha prenotato 1 navetta

INSERT INTO invoices (booking_id, total_amount, payment_status) VALUES
(1, 600.00, 'paid'),
(2, 1570.00, 'paid');

INSERT INTO reviews (user_id, room_category_id, rating, comment) VALUES
(3, 2, 5, 'Soggiorno fantastico, la camera era pulitissima, accogliente e il personale gentilissimo in ogni momento.');

INSERT INTO maintenance_tickets (room_id, reported_by_user_id, status_id, issue_description) VALUES
(6, 2, 2, 'Il condizionatore perde gocce d\'acqua, contattato il tecnico di turno.'),
(2, 2, 3, 'Sostituzione lampadina fulminata in bagno completata con successo.');

-- 6. Popolamento Prenotazioni Ristorante
INSERT INTO restaurant_reservations (user_id, reservation_date, meal_type, reservation_time, guests, status) VALUES
(3, '2026-08-01', 'Cena', '20:30', 2, 'Confirmed'),
(4, '2026-08-02', 'Pranzo', '13:00', 4, 'Pending');