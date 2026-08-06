<<<<<<< HEAD
import * as React from "react"

const MOBILE_BREAKPOINT = 768

export function useIsMobile() {
  const [isMobile, setIsMobile] = React.useState<boolean | undefined>(undefined)

  React.useEffect(() => {
    const mql = window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`)
    const onChange = () => {
      setIsMobile(window.innerWidth < MOBILE_BREAKPOINT)
    }
    mql.addEventListener("change", onChange)
    setIsMobile(window.innerWidth < MOBILE_BREAKPOINT)
    return () => mql.removeEventListener("change", onChange)
  }, [])

  return !!isMobile
=======
import * as React from "react";

const MOBILE_BREAKPOINT = 768;

function subscribe(callback: () => void) {
    const mql = window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);
    mql.addEventListener("change", callback);

    return () => mql.removeEventListener("change", callback);
}

function getSnapshot() {
    return window.innerWidth < MOBILE_BREAKPOINT;
}

function getServerSnapshot() {
    return false;
}

export function useIsMobile() {
    return React.useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
>>>>>>> origin
}
