import { onScopeDispose, ref, shallowRef } from 'vue';

type RequestWindowOptions = {
    width?: number;
    height?: number;
    disallowReturnToOpener?: boolean;
    preferInitialWindowPlacement?: boolean;
};

type DocumentPictureInPicture = {
    requestWindow: (options?: RequestWindowOptions) => Promise<Window>;
    window: Window | null;
};

declare global {
    interface Window {
        documentPictureInPicture?: DocumentPictureInPicture;
    }
}

export function usePictureInPictureWindow() {
    const isSupported =
        typeof window !== 'undefined' &&
        'documentPictureInPicture' in window;

    const isOpen = ref(false);
    const pipBody = shallowRef<HTMLElement | null>(null);
    let pipWindow: Window | null = null;

    // The PiP window is a separate document, so Tailwind utility rules and
    // CSS variables from the parent must be cloned into it manually.
    const copyStyles = (target: Window) => {
        for (const sheet of Array.from(document.styleSheets)) {
            try {
                const cssText = Array.from(sheet.cssRules)
                    .map((rule) => rule.cssText)
                    .join('\n');
                const style = target.document.createElement('style');
                style.textContent = cssText;
                target.document.head.appendChild(style);
            } catch {
                if (!sheet.href) {
                    continue;
                }
                const link = target.document.createElement('link');
                link.rel = 'stylesheet';
                link.type = sheet.type;
                link.media = sheet.media.mediaText;
                link.href = sheet.href;
                target.document.head.appendChild(link);
            }
        }
    };

    const handleClose = () => {
        pipWindow = null;
        pipBody.value = null;
        isOpen.value = false;
    };

    const close = () => {
        if (pipWindow) {
            pipWindow.close();
        }
        handleClose();
    };

    const open = async (options: RequestWindowOptions = {}) => {
        if (!isSupported || isOpen.value) {
            return;
        }

        const target = await window.documentPictureInPicture!.requestWindow({
            width: 360,
            height: 260,
            ...options,
        });

        copyStyles(target);

        if (document.documentElement.classList.contains('dark')) {
            target.document.documentElement.classList.add('dark');
        }

        target.document.body.classList.add(
            'bg-background',
            'text-foreground',
            'antialiased',
        );

        target.addEventListener('pagehide', handleClose);

        pipWindow = target;
        pipBody.value = target.document.body;
        isOpen.value = true;
    };

    onScopeDispose(() => {
        close();
    });

    return {
        isSupported,
        isOpen,
        pipBody,
        open,
        close,
    };
}
