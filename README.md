# BAI21113 SE Project
# Project-Management-System

This is the RU Software Engineering project. It is the enhanced and final version of the previous assignment. The goal of the system is to help employees manage and track their projects, enabling them to complete their work on time.

## Introduction
This system is called ‘ProManage’, which represents organizing, assigning, and delivering. This name also reflects the system's goal of providing a professional and efficient way to manage projects, teams, and tasks. It combines the concepts of project management, productivity, and professional collaboration into a simple and memorable name.

## Team Members

- CHOW KAI JIE BAI_A2009F-2509001
- CHEW SEE YUAN BIT_A2201F-2509002
- GUO RUNTING BDS_B2201F-2505001

## Team Members Role

| Role | Team Member | Responsibilities |
|------|-------------|------------------|
| Product Owner & Team Leader | CHOW KAI JIE | Duplicate latest source code into new repository, develop enhancement system architecture diagram, manage the Kanban board and backlog and involve into development team |
| Developer 1 | CHEW SEE YUAN | Part of the core development team; pull assigned tasks from the Kanban board backlog, implement features, and conduct local testing. |
| Developer 2 | GUO RUNTING | Part of the core development team; pull assigned tasks from the Kanban board backlog, implement features, and conduct local testing. |

## Technologies Used

The project is built using the following technologies:

- **HTML** - Structure and markup
- **CSS** - Styling and layout
- **JavaScript** - Client-side interactivity
- **PHP** - Server-side logic and backend
- **MySQL** - Database management (via XAMPP)

## Quick Start Guide

### Prerequisites

1. Install [XAMPP](https://www.apachefriends.org/) on your machine
2. Have Git installed for cloning the repository

### Setup Steps

1. **Install XAMPP**
   - Download and install XAMPP from the official website
   - Start the Apache and MySQL services from the XAMPP Control Panel

2. **Clone the Repository**
   - Open your terminal/command prompt
   - Navigate to the XAMPP `htdocs` folder:
     ```
     cd C:\xampp\htdocs
     ```
   - Clone the project:
     ```
     git clone https://github.com/KJIE-430-XX/Project-Management-System.git
     ```

3. **Setup Database Tables**
   - Open your cmd and go to the folder:
     ```
     C:\xampp\php\php.exe database/setup.php
     ```
   - This will create the database and all necessary tables automatically
   - You should see a success message confirming the database setup

4. **Access the Application**
   - Open your browser and go to:
     ```
     http://localhost/Project-Management-System/
     ```
   - You're all set! Start creating and tracking your tasks

### Repository

- **GitHub**: [https://github.com/KJIE-430-XX/Project-Management-System.git]

## Development Setup

This project uses **XAMPP** to provide a local development environment with Apache, PHP, and MySQL support.

## Agile Kanban Development Phases

### Phase 1: Architectural Blueprinting & Repository Setup
- Finalized the refined system architecture diagram for Version 2 features.
- Initialized the team Kanban board and loaded the product backlog with cards based on user stories.
- Duplicated the baseline code from Assignment 1 into this clean, dedicated repository.

### Phase 2: Kanban-Driven Execution & Pull Request (PR) Merging
- Pulled tasks asynchronously from the "To Do" column into "In Progress" according to personal availability.
- Implemented core system features via isolated feature branches and submitted Pull Requests.
- Conducted local XAMPP testing, addressed mid-way feedback regarding progress bars and project editing, and merged stable code into `main`.

### Phase 3: System Stabilization, Verification & Deployment
- Conducted full end-to-end testing across the unified system to guarantee a bug-free build.
- Documented requirements traceability and finalized technical reporting.
- Executed final system deployment to the production environment.


## Features

### 🏢 Workspaces & Folder Organization
- Create custom user workspaces to group and classify multiple project records.
- Seamlessly transition project views between Grid View and List View formats.

### 📋 Task Board & Lifecycle Control
- Create, live-edit, and delete individual tasks directly from an interactive right-side drawer overlay.
- Keep boards clean with a dedicated completed tasks area that hides finished items by default unless expanded.
- Visual progress tracking bars computing complete-to-total ratios, exact counts, and progress percentages.

### 💬 Live Collaboration & Alerts
- Direct task-level messaging feeds enabling seamless communication between Project PIC and members.
- Visual red badge notification indicators on task cards highlighting unread comment updates.

### 🛡️ Data Protection & Account Security
- Two-stage deletion protection: Soft-delete projects safely to a Trash View with options to restore or permanently hard-delete records.
- Account profile personalization editor (update display name, email, and credentials).
- Secure account entry recovery utilizing a temporary, valid OTP via email verification.