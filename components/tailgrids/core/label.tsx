"use client";

import {
  Label as AriaLabel
  
} from "react-aria-components";
import type {LabelProps as AriaLabelProps} from "react-aria-components";
import { cn } from "@/utils/cn";

export interface LabelProps extends AriaLabelProps {}

export function Label({ className, ...props }: LabelProps) {
  return (
    <AriaLabel
      className={cn("text-sm font-normal text-text-50", className)}
      {...props}
    />
  );
}
