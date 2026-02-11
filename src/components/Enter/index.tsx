import React from 'react';
import ReactDOM from 'react-dom/client';
import { Stack, TextField, Button } from '@mui/material';

const Enter = () => {
    return (
        <div>
            <h1>予算入力画面</h1>
            <Stack direction="column" spacing={2}>
                <TextField label="予算" variant="outlined" fullWidth />
                <Button variant="contained" color="primary">次へ</Button>
                <Button variant="contained" color="secondary">キャンセル</Button>
            </Stack>
        </div>
    );
};
