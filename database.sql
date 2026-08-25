CREATE TABLE students (
    studentID  VARCHAR(9)   NOT NULL PRIMARY KEY,
    name       VARCHAR(60)  NOT NULL,
    email      VARCHAR(80)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    department VARCHAR(50)  NOT NULL,
    gender     ENUM('Male','Female') NOT NULL
);

CREATE TABLE organizers (
    organizerID VARCHAR(9)   NOT NULL PRIMARY KEY,
    name        VARCHAR(60)  NOT NULL,
    email       VARCHAR(80)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        VARCHAR(40)  NOT NULL
);

CREATE TABLE admins (
    adminID  VARCHAR(9)   NOT NULL PRIMARY KEY,
    name     VARCHAR(60)  NOT NULL,
    email    VARCHAR(80)  NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE events (
    eventID     INT AUTO_INCREMENT PRIMARY KEY,
    organizerID VARCHAR(9)   NOT NULL,              
    title       VARCHAR(100) NOT NULL,
    description TEXT,
    date        DATETIME     NOT NULL,
    location    VARCHAR(100) NOT NULL,
    capacity    INT          NOT NULL DEFAULT 50,
    department  VARCHAR(100) NOT NULL DEFAULT 'All',
    gender      ENUM('Male','Female','Both') NOT NULL DEFAULT 'Both',
    is_paid     ENUM('Yes','No') NOT NULL DEFAULT 'No',
    price       DECIMAL(8,2) DEFAULT 0.00,
    status      ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organizerID) REFERENCES organizers(organizerID) ON DELETE CASCADE
);

CREATE TABLE registrations (
    registrationID   INT AUTO_INCREMENT PRIMARY KEY,
    studentID        VARCHAR(9) NOT NULL,
    eventID          INT        NOT NULL,
    registrationDate TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
    status           ENUM('confirmed','cancelled') DEFAULT 'confirmed',
    FOREIGN KEY (studentID) REFERENCES students(studentID) ON DELETE CASCADE,
    FOREIGN KEY (eventID)   REFERENCES events(eventID)     ON DELETE CASCADE
);

CREATE TABLE approvals (
    approvalID   INT AUTO_INCREMENT PRIMARY KEY,
    eventID      INT          NOT NULL,
    adminID      VARCHAR(9)   NOT NULL,             
    decision     ENUM('approved','rejected') NOT NULL,
    decisionDate TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    remarks      TEXT,
    FOREIGN KEY (eventID)  REFERENCES events(eventID) ON DELETE CASCADE,
    FOREIGN KEY (adminID)  REFERENCES admins(adminID) ON DELETE CASCADE
);

CREATE TABLE requests (
    requestID   INT AUTO_INCREMENT PRIMARY KEY,
    studentID   VARCHAR(9)  NULL,
    organizerID VARCHAR(9)  NULL,                  
    senderType  ENUM('student','organizer') NOT NULL,
    category    VARCHAR(100) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    message     TEXT         NOT NULL,
    status      ENUM('Pending','Resolved') NOT NULL DEFAULT 'Pending',
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (studentID)   REFERENCES students(studentID)     ON DELETE SET NULL,
    FOREIGN KEY (organizerID) REFERENCES organizers(organizerID) ON DELETE SET NULL
);

CREATE TABLE responses (
    responseID   INT AUTO_INCREMENT PRIMARY KEY,
    requestID    INT        NOT NULL,
    adminID      VARCHAR(9) NOT NULL,              
    reply        TEXT       NOT NULL,
    status_after ENUM('Pending','Resolved') NOT NULL,
    replied_at   TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requestID) REFERENCES requests(requestID) ON DELETE CASCADE,
    FOREIGN KEY (adminID)   REFERENCES admins(adminID)     ON DELETE CASCADE
);

-- Default admin (password: majed123)
INSERT INTO admins (adminID, name, email, password) VALUES
('100000001', 'Admin', 'admin@jazanu.edu.sa', '$2b$12$IZVGwaCLVuHrBsOrqNHhLO1OpAgCFc.c21g8ujG3L14o.lvNgpwq.');

-- Demo organizer (password: majed123)
INSERT INTO organizers (organizerID, name, email, password, role) VALUES
('200000001', 'CS Club', 'csclub@jazanu.edu.sa', '$2b$12$IZVGwaCLVuHrBsOrqNHhLO1OpAgCFc.c21g8ujG3L14o.lvNgpwq.', 'Student Club');
