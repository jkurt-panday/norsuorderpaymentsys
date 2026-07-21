import toast from 'react-hot-toast';

type ToastType = 'success' | 'error' | 'loading';

interface ToastOptions {
  duration?: number;
  position?: 'top-right' | 'top-center' | 'top-left' | 'bottom-right' | 'bottom-center' | 'bottom-left';
}

export function useToast() {
  const showToast = (message: string, type: ToastType = 'success', options?: ToastOptions) => {
    const { duration = 4000, position = 'top-right' } = options || {};

    switch (type) {
      case 'success':
        toast.success(message, { duration, position });
        break;
      case 'error':
        toast.error(message, { duration, position });
        break;
      case 'loading':
        return toast.loading(message, { position });
      default:
        toast(message, { duration, position });
    }
  };

  const dismissToast = (toastId?: string) => {
    if (toastId) {
      toast.dismiss(toastId);
    } else {
      toast.dismiss();
    }
  };

  return { showToast, dismissToast };
}