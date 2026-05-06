# Subscription Based Membership System

A full-stack web application built with the MERN stack (MongoDB, Express, React, Node.js) featuring tiered access, JWT authentication, and Stripe payment integration.

## Features
- **User Authentication**: JWT-based login/signup.
- **Role-based Access**: Separate flows for Users and Admins.
- **Subscription Tiers**: Free, Basic, Premium, and Enterprise plans.
- **Payment Integration**: Stripe Checkout for purchases and webhooks for real-time tier upgrades.
- **Admin Dashboard**: View users, manage plans, and see transactions.

## Tech Stack
- **Frontend**: React (Vite), Tailwind CSS, React Router, Axios
- **Backend**: Node.js, Express.js, Mongoose
- **Database**: MongoDB
- **Payments**: Stripe SDK

## Getting Started

### Prerequisites
- Node.js installed
- MongoDB installed locally or MongoDB Atlas URL.
- Stripe Account for test keys.

### 1. Setup Backend
```bash
cd backend
npm install
```
Configure `.env` in the `backend/` directory:
```env
PORT=5000
MONGO_URI=mongodb://localhost:27017/subscription-app
JWT_SECRET=yoursecretkey
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
FRONTEND_URL=http://localhost:5173
```
Start backend:
```bash
npm run dev
```

### 2. Setup Frontend
```bash
cd frontend
npm install
```
Start frontend:
```bash
npm run dev
```

### 3. Setup Stripe Webhook (Local Testing)
Use Stripe CLI to forward events to your local server:
```bash
stripe listen --forward-to localhost:5000/api/payments/webhook
```
Copy the webhook signing secret from the CLI output and paste it into `STRIPE_WEBHOOK_SECRET` in `backend/.env`.

### 4. Docker (Optional)
To run the entire stack with Docker Compose:
```bash
docker-compose up --build
```

## API Documentation
| Method | Endpoint | Access | Description |
|---|---|---|---|
| POST | `/api/auth/register` | Public | Register new user |
| POST | `/api/auth/login` | Public | Authenticate user & get token |
| GET | `/api/auth/profile` | Private | Get user profile |
| GET | `/api/plans` | Public | Get all plans |
| POST | `/api/plans` | Admin | Create a plan |
| GET | `/api/subscriptions/my` | Private | Get user's active subscription |
| POST | `/api/payments/create-checkout-session`| Private | Initiate Stripe checkout |
| GET | `/api/admin/users` | Admin | View all users |
| GET | `/api/admin/transactions` | Admin | View all payments |
