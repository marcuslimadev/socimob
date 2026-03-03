import { createElement, useEffect, useMemo, useRef, useState } from "react";

type ChatPosition =
  | "bottom-right"
  | "bottom-left"
  | "top-right"
  | "top-left"
  | "center-right"
  | "center-left";

const DEFAULT_WIDGET_CDN =
  "https://cdn.jsdelivr.net/gh/logspace-ai/langflow-embedded-chat@v1.0.7/dist/build/static/js/bundle.min.js";
const SCRIPT_ID = "langflow-embedded-chat-script";

let widgetScriptPromise: Promise<void> | null = null;

function loadWidgetScript(src: string): Promise<void> {
  if (typeof document === "undefined") {
    return Promise.resolve();
  }

  if (widgetScriptPromise) {
    return widgetScriptPromise;
  }

  widgetScriptPromise = new Promise<void>((resolve, reject) => {
    const existing = document.getElementById(SCRIPT_ID) as HTMLScriptElement | null;

    if (existing) {
      if ((window as Window & { customElements: CustomElementRegistry }).customElements.get("langflow-chat")) {
        resolve();
        return;
      }

      existing.addEventListener("load", () => resolve(), { once: true });
      existing.addEventListener("error", () => reject(new Error("Failed to load Langflow widget script")), {
        once: true,
      });
      return;
    }

    const script = document.createElement("script");
    script.id = SCRIPT_ID;
    script.src = src;
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error("Failed to load Langflow widget script"));

    document.head.appendChild(script);
  });

  return widgetScriptPromise;
}

function setOptionalAttribute(element: HTMLElement, name: string, value?: string | null) {
  if (!value) {
    element.removeAttribute(name);
    return;
  }

  element.setAttribute(name, value);
}

export interface LangflowChatWidgetProps {
  enabled?: boolean;
  scriptUrl?: string;
  hostUrl: string;
  flowId: string;
  apiKey?: string;
  chatPosition?: ChatPosition;
  startOpen?: boolean;
  sessionId?: string;
  additionalHeaders?: Record<string, string>;
  chatInputs?: Record<string, string>;
  placeholder?: string;
  windowTitle?: string;
  className?: string;
  onReady?: () => void;
  onError?: (error: Error) => void;
}

export default function LangflowChatWidget({
  enabled = true,
  scriptUrl = DEFAULT_WIDGET_CDN,
  hostUrl,
  flowId,
  apiKey,
  chatPosition = "bottom-right",
  startOpen = false,
  sessionId,
  additionalHeaders,
  chatInputs,
  placeholder,
  windowTitle,
  className,
  onReady,
  onError,
}: LangflowChatWidgetProps) {
  const widgetRef = useRef<HTMLElement | null>(null);
  const [isReady, setIsReady] = useState(false);

  const additionalHeadersJson = useMemo(
    () => (additionalHeaders ? JSON.stringify(additionalHeaders) : undefined),
    [additionalHeaders]
  );

  const chatInputsJson = useMemo(() => (chatInputs ? JSON.stringify(chatInputs) : undefined), [chatInputs]);

  useEffect(() => {
    if (!enabled) {
      setIsReady(false);
      return;
    }

    let cancelled = false;

    loadWidgetScript(scriptUrl)
      .then(() => {
        if (cancelled) {
          return;
        }

        setIsReady(true);
        onReady?.();
      })
      .catch((error: unknown) => {
        if (cancelled) {
          return;
        }

        const normalizedError = error instanceof Error ? error : new Error("Failed to initialize Langflow widget");
        setIsReady(false);
        onError?.(normalizedError);
      });

    return () => {
      cancelled = true;
    };
  }, [enabled, scriptUrl, onReady, onError]);

  useEffect(() => {
    if (!isReady || !widgetRef.current) {
      return;
    }

    const element = widgetRef.current;
    element.setAttribute("host_url", hostUrl);
    element.setAttribute("flow_id", flowId);
    element.setAttribute("chat_position", chatPosition);
    element.setAttribute("start_open", startOpen ? "true" : "false");

    setOptionalAttribute(element, "api_key", apiKey);
    setOptionalAttribute(element, "session_id", sessionId);
    setOptionalAttribute(element, "additional_headers", additionalHeadersJson);
    setOptionalAttribute(element, "chat_inputs", chatInputsJson);
    setOptionalAttribute(element, "input_placeholder", placeholder);
    setOptionalAttribute(element, "window_title", windowTitle);
  }, [
    isReady,
    hostUrl,
    flowId,
    apiKey,
    chatPosition,
    startOpen,
    sessionId,
    additionalHeadersJson,
    chatInputsJson,
    placeholder,
    windowTitle,
  ]);

  if (!enabled || !isReady) {
    return null;
  }

  return createElement("langflow-chat", { ref: widgetRef, className });
}
