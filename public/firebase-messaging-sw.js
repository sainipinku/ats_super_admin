importScripts("https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js");
importScripts("https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js");

// firebase.initializeApp({
//     apiKey: "AIzaSyBkQgbQGz89EXcP-27numZQNQRZ-hdEjwQ",
//     authDomain: "transport-issue-tracking-app.firebaseapp.com",
//     databaseURL: "https://transport-issue-tracking-app-default-rtdb.asia-southeast1.firebasedatabase.app",
//     projectId: "transport-issue-tracking-app",
//     storageBucket: "transport-issue-tracking-app.firebasestorage.app",
//     messagingSenderId: "216006217728",
//     appId: "1:216006217728:web:50e95d6a6c75b7b19ec8e5",
//     measurementId: "G-6W91Q8Y21Z",
//     vapidApiKey:"BLyVeD049Xdz0T5hdZvnidfy3bt75ywkhmiYymeR_V_-A2i2KD63GB__-SqdwABhNfwIJQtnXaPO4Xcwkr0g31w"
// });
const firebaseConfig = {
  apiKey: "AIzaSyDS343wZE0yoRWpm8s_Jzb3iqV91zwodBE",
  authDomain: "test-1bb9e.firebaseapp.com",
  databaseURL: "https://test-1bb9e-default-rtdb.firebaseio.com",
  projectId: "test-1bb9e",
  storageBucket: "test-1bb9e.firebasestorage.app",
  messagingSenderId: "321301079604",
  appId: "1:321301079604:web:5449a46daa5ba43afd0c61",
  measurementId: "G-T06HBY939X"
};

const messaging = firebase.messaging();


messaging.onBackgroundMessage(function (payload) {
    console.log(1111111);
    console.log('[firebase-messaging-sw.js] Background message ', payload);

    const title = payload.notification?.title || payload.data?.title || 'Notification';
    const options = {
        body: payload.notification?.body || payload.data?.body,
        icon: payload.notification?.icon || payload.data?.image || '/assets/logo.png',
        data: payload.data
    };

    self.registration.showNotification(title, options);
});

self.addEventListener('notificationclick', (event) => {
    console.log('Notification click event:', event);
    event.notification.close();

    const customData = event.data || event.notification.data || {};
    const defaultUrl = self.location.origin;
    const targetUrl = customData?.url ? (new URL(customData?.url, defaultUrl)).href : defaultUrl;
    console.log('customData', customData);
    console.log('customDataURl', customData?.url);

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (let client of clientList) {
                if (client.url.includes(targetUrl) && 'focus' in client) {
                    return client.focus();
                }
            }
            return clients.openWindow(targetUrl);
        })
    );
});
