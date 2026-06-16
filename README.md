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
| Product Owner & Scrum Master | CHOW KAI JIE | Duplicate latest source code into new repository, planning for the Scrum, allocate the job scope to team members |
| Developer 1 | CHEW SEE YUAN | TBC |
| Developer 2 | GUO RUNTING | TBC |

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

## SCRUM Development Iterations

### Iteration 1: Week 1 ()
**Project Move Place and Planning**


### Iteration 2: Week 2 ()
**Create and Read Features**


## Features
**Core Feature**
Project Management System
- Create and manage projects
- Add members to project

Task Management System
- Create tasks under project
- Set priority, status and deadline to the task
- Assign tasks to project's member

**Supporting Feature**
User Authentication
- Login
- Register