import { useTheme } from "next-themes"
import { Toaster as Sonner, type ToasterProps } from "sonner"
import { CircleCheckIcon, InfoIcon, TriangleAlertIcon, OctagonXIcon, Loader2Icon } from "lucide-react"

const Toaster = ({ ...props }: ToasterProps) => {
  const { theme = "system" } = useTheme()

  return (
    <Sonner
      theme={theme as ToasterProps["theme"]}
      richColors={true}
      className="toaster group"
      icons={{
        success: (
          <CircleCheckIcon className="size-4" />
        ),
        info: (
          <InfoIcon className="size-4" />
        ),
        warning: (
          <TriangleAlertIcon className="size-4" />
        ),
        error: (
          <OctagonXIcon className="size-4" />
        ),
        loading: (
          <Loader2Icon className="size-4 animate-spin" />
        ),
      }}
      style={
        {
          "--normal-bg": "var(--popover)",
          "--normal-text": "var(--popover-foreground)",
          "--normal-border": "var(--border)",
          "--border-radius": "var(--radius)",
          "--success-bg": "#003f7d",
          "--success-border": "#002d5b",
          "--success-text": "#f8fafc",
          "--error-bg": "#c2410c",
          "--error-border": "#9a3412",
          "--error-text": "#f8fafc",
          "--warning-bg": "#f59e0b",
          "--warning-border": "#d97706",
          "--warning-text": "#0f172a",
          "--info-bg": "#0f172a",
          "--info-border": "#334155",
          "--info-text": "#f8fafc",
        } as React.CSSProperties
      }
      toastOptions={{
        classNames: {
          toast: "cn-toast",
          success: "cn-toast-success",
          error: "cn-toast-error",
          warning: "cn-toast-warning",
          info: "cn-toast-info",
        },
      }}
      {...props}
    />
  )
}

export { Toaster }
