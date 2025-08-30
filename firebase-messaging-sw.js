importScripts("https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js");
importScripts("https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js");

firebase.initializeApp({
  apiKey: "AIzaSyBbTe4CZM8mUqsJUyFBIjcSc3w4rvIbmzc",
  authDomain: "robuxvex.firebaseapp.com",
  projectId: "robuxvex",
  storageBucket: "robuxvex.firebasestorage.app",
  messagingSenderId: "291500837886",
  appId: "1:291500837886:web:08ae60ca6454c209328eaf",
});

const messaging = firebase.messaging();
