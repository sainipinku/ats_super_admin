import { createContext, useState, useEffect, useContext } from "react";
import { initializeApp } from "firebase/app";
import { getMessaging, getToken, onMessage } from "firebase/messaging";
// import {getAuth, onAuthStateChanged, signInWithCustomToken} from "firebase/auth";
import axios from "axios";
import Modal from "@/Components/Modal";
import { router, usePage } from "@inertiajs/react";
import { v4 } from "uuid";
import { getFirestore } from "firebase/firestore";

export const FirebaseAuthContext = createContext();

export const useFirebaseAuth = () => {
    return useContext(FirebaseAuthContext);
};

export const FirebaseAuthProvider = ({ children }) => {
    const { props } = usePage();

    const [open, setOpen] = useState(false);

    const [browserId, setBrowserId] = useState(null);

    const firebaseConfig = props.creds;

    let user;

    if (props?.auth?.guard == "admin") {
        user = props?.auth?.user;
    } else {
        user = props?.auth;
    }

    const [db, setDb] = useState(null);

    const is_notification_enabled = props?.is_notification_enabled || [""];

    const [process, setProcess] = useState(false);

    const initPush = () => {
        setTimeout(() => {
            if ("Notification" in window) {
                const perm_state =
                    (Notification && Notification?.permission) || "Undefined";
                if (perm_state == "denied") {
                    return;
                }
                if (perm_state == "granted") {
                    const storedBrowserId = localStorage?.getItem("_tkBrowserId");
                    if (!is_notification_enabled.includes(storedBrowserId)) {
                        setOpen(true);
                    } else {
                        requestPermissionAndGetToken();
                    }

                    return;
                }
            } else {
            }
        }, 100);
    };

    const askLater = (ttl = 7 * 24 * 60 * 60 * 1000) => {
        setOpen(false);
        const expire = new Date().getTime() + ttl;
        localStorage.setItem("_pushAskLater", expire);
    };

    const requestPermissionAndGetToken = async () => {
        setProcess(true);
        try {
            const permission = await Notification.requestPermission();
            if (permission === "granted") {
                const app = initializeApp(firebaseConfig);
                const DBInfo = getFirestore(app);
                setDb(DBInfo);
                const storedBrowserId = localStorage?.getItem("_tkBrowserId");
                const finalBrowserId = browserId || storedBrowserId;
                const messaging = getMessaging(app);
                const currentToken = await getToken(messaging, {
                    vapidKey: firebaseConfig.vapidApiKey,
                });
                if (currentToken) {
                    if (user?.fcm_token?.token !== currentToken) {
                        let saveTokenRoute;
                        switch (props?.auth?.guard) {
                            case "admin":
                                saveTokenRoute = "admin.fcm.saveToken";
                                break;
                            case "superadmin":
                                saveTokenRoute = "super.fcm.saveToken";
                                break;
                            case "member":
                                saveTokenRoute = "member.fcm.saveToken";
                                break;
                            default:
                                saveTokenRoute = "fcm.saveToken";
                        }
                        router.post(
                            route(saveTokenRoute),
                            {
                                token: currentToken,
                                browserId: finalBrowserId,
                            },
                            {
                                preserveState: true,
                                onSuccess: () => {
                                    setProcess(false);
                                    setOpen(false);
                                },
                                onError: (errors) => {
                                    setProcess(false);
                                }
                            }
                        );
                    } else {
                        setProcess(false);
                        setOpen(false);
                    }
                } else {
                    setProcess(false);
                }
            } else {
                setProcess(false);
            }
            setOpen(false);
        } catch (error) {
            setProcess(false);
            setOpen(false);
        }
    };

    useEffect(() => {
        if (!firebaseConfig || !user) {
            return;
        }
        const app = initializeApp(firebaseConfig);
        const messaging = getMessaging(app);
        if ("serviceWorker" in navigator) {
            navigator.serviceWorker
                .register("/firebase-messaging-sw.js")
                .then((registration) => {
                })
                .catch((err) => {
                });
        } else {
        }
        const unsubscribe = onMessage(messaging, (payload) => {
            const type = payload.data?.type || "default";
            const title =
                payload.notification?.title ||
                payload.data?.title ||
                "Notification";
            const body = payload.notification?.body || payload.data?.body;
            const icon =
                payload.notification?.icon ||
                payload.data?.image ||
                "/assets/your_logo.png";
            const options = {
                body,
                icon,
                data: payload.data,
                requireInteraction: type === "sos-reported",
                vibrate: type === "sos-reported" ? [300, 100, 300] : undefined,
            };
            const notification = new Notification(title, options);
        });

        return () => {
            unsubscribe();
        };
    }, [firebaseConfig, user]);

    useEffect(() => {
        if (localStorage.getItem("_tkBrowserId")) {
            const storedId = localStorage.getItem("_tkBrowserId");
            setBrowserId(storedId);
        } else {
            const uuid = v4();
            localStorage.setItem("_tkBrowserId", uuid);
            setBrowserId(uuid);
        }

        if (localStorage.getItem("_pushAskLater")) {
            const askLaterTime = localStorage.getItem("_pushAskLater");
            if (askLaterTime > new Date().getTime()) {
                return;
            } else {
                localStorage.removeItem("_pushAskLater");
                initPush();
            }
        } else {
            initPush();
        }
    }, []);

    return (
        <FirebaseAuthContext.Provider value={{}}>
            {children}
            <Modal
                show={open}
                maxWidth="md"
                topCloseButton={true}
                handleTopClose={() => {
                    askLater();
                }}
            >
                <div className="p-4 md:p-5 text-center dark:bg-gray-700">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill={"none"}
                        className="size-12 text-tk mx-auto mb-4"
                    >
                        <path
                            d="M2.52992 14.394C2.31727 15.7471 3.268 16.6862 4.43205 17.1542C8.89481 18.9486 15.1052 18.9486 19.5679 17.1542C20.732 16.6862 21.6827 15.7471 21.4701 14.394C21.3394 13.5625 20.6932 12.8701 20.2144 12.194C19.5873 11.2975 19.525 10.3197 19.5249 9.27941C19.5249 5.2591 16.1559 2 12 2C7.84413 2 4.47513 5.2591 4.47513 9.27941C4.47503 10.3197 4.41272 11.2975 3.78561 12.194C3.30684 12.8701 2.66061 13.5625 2.52992 14.394Z"
                            stroke="currentColor"
                            strokeWidth="1.5"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        ></path>
                        <path
                            d="M9 21C9.79613 21.6219 10.8475 22 12 22C13.1525 22 14.2039 21.6219 15 21"
                            stroke="currentColor"
                            strokeWidth="1.5"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        ></path>
                    </svg>
                    <h3 className="mb-5 text-lg font-normal text-gray-500 dark:text-gray-400">
                        For instant & realtime updates, please allow to send
                        notifications.
                    </h3>
                    {/* <span className="text-xs text-red-600 mb-4">{errors?.message}</span> */}
                    <div className="inline-flex items-center">
                        <button
                            type="button"
                            className="text-white bg-red-600 hover:bg-red-800 focus:ring-0 focus:outline-none dark:focus:ring-0 font-medium rounded-lg text-sm px-5 py-2.5 text-center"
                            onClick={() => {
                                requestPermissionAndGetToken();
                            }}
                            disabled={process}
                        >
                            {process ? "Processing..." : "Yes, Allow"}
                        </button>
                    </div>
                    <button
                        onClick={(e) => askLater()}
                        type="button"
                        className="py-2.5 px-5 ms-3 text-sm font-medium text-gray-900 focus:outline-none bg-white rounded-lg border border-gray-200 hover:bg-gray-100 hover:text-tks-700 focus:z-10 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700"
                    >
                        No, Cancel
                    </button>
                </div>
            </Modal>
        </FirebaseAuthContext.Provider>
    );
};
