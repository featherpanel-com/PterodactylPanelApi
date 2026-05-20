<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { AlertCircle } from "lucide-vue-next";
</script>

<template>
  <div class="p-4 md:p-8 flex items-center justify-center min-h-[50vh]">
    <div class="mx-auto max-w-md w-full">
      <Card class="border-destructive">
        <CardHeader>
          <div class="flex items-center gap-3 mb-2">
            <AlertCircle class="h-6 w-6 text-destructive" />
            <CardTitle class="text-2xl text-destructive">Missing Server UUID</CardTitle>
          </div>
          <CardDescription>
            This page requires a server context to function.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="space-y-4">
            <p class="text-muted-foreground text-sm">
              Open this addon from a FeatherPanel server page so the
              <code class="px-1.5 py-0.5 bg-muted rounded text-xs">serverUuid</code>
              parameter is available.
            </p>
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>
