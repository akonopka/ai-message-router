<?php

namespace App\Agent;

/**
 * Shared per-request marker (Symfony services are shared by default) letting
 * the controller detect whether the AI agent actually invoked
 * SendDepartmentEmailTool during its reasoning. The LLM does not reliably
 * call the tool for every input (observed empirically — see README) despite
 * explicit prompt instructions, so this backs a deterministic fallback in
 * the controller, not a replacement for the agent's own decision-making.
 */
class ToolInvocationTracker
{
    public bool $invoked = false;
    public bool $sent = false;
}
